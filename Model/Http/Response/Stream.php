<?php
namespace MageOS\AdminAssistant\Model\Http\Response;

use Magento\Framework\App\Response\HttpInterface as HttpResponseInterface;
use Magento\Framework\Controller\AbstractResult;
use Psr\Http\Message\StreamInterface;

class Stream extends AbstractResult
{

    /**
     * @var mixed
     */
    protected $source;

    protected string $streamedContent = '';

    protected array $callbacks = [];

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @param \Magento\Framework\Serialize\Serializer\Json $serializer
     */
    public function __construct(
        \Magento\Framework\Serialize\Serializer\Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * Set json data
     *
     * @param mixed $data
     * @param boolean $cycleCheck Optional; whether or not to check for object recursion; off by default
     * @param array $options Additional options used during encoding
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setData($data, $cycleCheck = false, $options = [])
    {
        $this->source = $data;
        return $this;
    }

    public function setSteamData(StreamInterface $stream) {

    }

    public function addCallback($callback)
    {
        $this->callbacks[] = $callback;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function render(HttpResponseInterface $response)
    {
        $response->setHeader('X-Accel-Buffering', 'no', true);
        $response->setHeader('Content-type', 'text/event-stream', true);
        $response->setHeader('Cache-Control', 'no-cache', true);
        //response has to be sent early to set streaming header before display
        $response->sendResponse();

        if($this->source instanceof StreamInterface) {
            while(!$this->source->eof()) {
                $text = $this->source->read(64);
                echo "data:" . json_encode(['text' => $text]) . "\n\n";
                $this->streamedContent .= $text;
                @ob_flush();
                flush();
            }
        } else {
            $this->streamedContent = $this->serializer->serialize($this->source);
            echo "data:" . $this->streamedContent . "\n\n";
            @ob_flush();
            flush();
        }

        // callback

        foreach ($this->callbacks as $callback) {
            if($result = $callback->execute($this->streamedContent)) {
                echo "data:" . json_encode($result) . "\n\n";
                @ob_flush();
                flush();
            }
        }

        return $this;
    }
}
