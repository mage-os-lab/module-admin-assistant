<?php
namespace MageOS\AdminAssistant\Controller\Adminhtml\Ai;

use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\MessageFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use \Magento\Framework\Serialize\Serializer\Json;
use MageOS\AdminAssistant\Api\BotInterface;
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
        private TextTableFactory $textTableFactory
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
        $lastSysMessage = '';
        $copyMessages =[];
        foreach ($post['messages'] ?? [] as $postMessage) {
            $message = $this->messageFactory->create();
            $message->role = ChatRole::from($postMessage['role'] === 'user' ? 'user' : 'assistant'); // TODO use a custom role class
            $message->content = $postMessage['text'];
            $messages[] = $message;
            if($postMessage['role'] == 'ai') {
                $lastSysMessage = $postMessage['text'];
            }
            $copyMessages[] = clone $message;
        }

        // @TODO refactor
        if($lastSysMessage && $result = $this->runQuery($lastSysMessage, $copyMessages)) {
            header("X-Accel-Buffering: no");
            header("Content-Type: text/event-stream");
            header("Cache-Control: no-cache");
            echo "data:" . json_encode($result) . "\n\n";
            @ob_flush();
            flush();
        }

        $result = [];
        try {
            $llmAnswer = $this->bot->answer($messages);

            //TODO: encapsulate the answer in a stream resopnse class
            header("X-Accel-Buffering: no");
            header("Content-Type: text/event-stream");
            header("Cache-Control: no-cache");
            $runQuery = false;
            $text = '';
            while(!$llmAnswer->eof()) {
                $prevText = $text;
                $text = $llmAnswer->read(64);
                echo "data:" . json_encode(['text' => $text]) . "\n\n";
                @ob_flush();
                flush();
                if(stristr($prevText . $text, '```sql')) {
                    $runQuery = true;
                }
            }
            if($runQuery) {
                echo "data:" . json_encode(['html' => '<div class="deep-chat-temporary-message"><button class="deep-chat-button deep-chat-suggestion-button" style="border: 1px solid green">Run Query</button></div>']) . "\n\n";
                @ob_flush();
                flush();
            }
            exit();
        }
        catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
            $result = [
                'error' => 'Sorry something is wrong, please try again' , $e->getMessage()
            ];
        }
        $answer->setData($result);
        return $answer;
    }

    protected function runQuery($message, $messages) {
        preg_match_all('/```sql(.*?)```/s', $message, $matches);
        $result = [];
        if($this->sqlRetry++ > 5) {
            return $result;
        }
        if(isset($matches[1][0]) && !empty($matches[1][0])) {
            $connection = $this->resourceConnection->getConnection();
            try {
                $result = $connection->fetchAll($matches[1][0]);
                $tt = $this->textTableFactory->create(['header' => null, 'content'=> $result]);
                $result = [
                    'text' => $tt->render(),
                ];
            }
            catch(\Exception $e) {
                $messages[] = $this->messageFactory->create(['role' => 'user', 'content' => 'The query failed with this error message: ' . $e->getMessage() , '; Please correct the query']);
                $answer = (string)$this->bot->answer($messages);
                $result = $this->runQuery($answer, $messages);
            }
        }
        return $result;
    }
}
