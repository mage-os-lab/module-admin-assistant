<?php
namespace MageOS\AdminAssistant\Controller\Adminhtml\Ai;

use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\MessageFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use \Magento\Framework\Serialize\Serializer\Json;
use MageOS\AdminAssistant\Api\BotInterface;
use MageOS\AdminAssistant\Model\Callback\Sql;
use MageOS\AdminAssistant\Model\Http\Response\Stream;
use Psr\Log\LoggerInterface;
use MageOS\AdminAssistant\Model\TextTableFactory;

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

    protected $sqlRetry = 0;

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
        private LoggerInterface $logger,
        private ResourceConnection $resourceConnection,
        private TextTableFactory $textTableFactory,
        private Stream $stream,
        private \MageOS\AdminAssistant\Model\Agent\Sql $sqlAgent,
        private Sql $sqlCallback,
    ) {
        $this->answerFactory = $answerFactory;
        parent::__construct($context);
    }

    /**
     * @return Stream
     */
    public function execute()
    {
        $post = $this->serializer->unserialize($this->getRequest()->getContent());
        $messages = [];
        $result = [];
        $lastSysMessage = '';
        foreach ($post['messages'] ?? [] as $postMessage) {
            $message = $this->messageFactory->create();
            $message->role = ChatRole::from($postMessage['role'] === 'user' ? 'user' : 'assistant'); // TODO use a custom role class
            $message->content = $postMessage['text'];
            $messages[] = $message;
            if($postMessage['role'] == 'ai') {
                $lastSysMessage = $postMessage['text'];
            }
        }

        // @TODO use an agent pool
        if($result = $this->sqlAgent->execute($lastSysMessage)) {
            $this->stream->setData($result);
        }
        else {
            try {
                $llmAnswer = $this->bot->answer($messages);
                $this->stream->setData($llmAnswer);
                $this->stream->addCallback($this->sqlCallback);
            }
            catch (\Exception $e) {
                $this->logger->warning($e->getMessage());
                $result = [
                    'error' => 'Sorry something is wrong, please try again' , $e->getMessage()
                ];
                $this->stream->setData($result);
            }
        }

        return $this->stream;
    }
}
