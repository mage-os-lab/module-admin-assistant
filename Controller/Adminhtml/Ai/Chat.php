<?php
namespace MageOS\AdminAssist\Controller\Adminhtml\Ai;

use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\MessageFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use \Magento\Framework\Serialize\Serializer\Json;
use MageOS\AdminAssist\Api\BotInterface;
use Psr\Log\LoggerInterface;

/**
 * Index action.
 */
class Chat extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $answerFactory;

    protected $chat;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $answerFactory,
        private MessageFactory $messageFactory,
        private Json $serializer,
        private BotInterface $bot,
        private LoggerInterface $logger
    ) {
        $this->answerFactory = $answerFactory;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        $answer = $this->answerFactory->create();
        $post = $this->serializer->unserialize($this->getRequest()->getContent());
        $messages = [];
        foreach ($post['messages'] ?? [] as $postMessage) {
            $message = $this->messageFactory->create();
            $message->role = ChatRole::from($postMessage['role'] === 'user' ? 'user' : 'assistant'); // TODO use a custom role class
            $message->content = $postMessage['text'];
            $messages[] = $message;
        }
        $result = [];
        try {
            $llmAnswer = $this->bot->answer($messages);

            //TODO: encapsulate the answer in a stream resopnse class
            header("X-Accel-Buffering: no");
            header("Content-Type: text/event-stream");
            header("Cache-Control: no-cache");
            while(!$llmAnswer->eof()) {
                echo "data:" . json_encode(['text' => $llmAnswer->read(64)]) . "\n\n";
                @ob_flush();
                flush();
            }
            exit();
        }
        catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
            $result = [
                'error' => 'Sorry something is wrong, please try again'
            ];
        }
        $answer->setData($result);
        return $answer;
    }
}
