<?php
namespace MageOS\AdminAssistant\Controller\Adminhtml\Ai;

use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\MessageFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Serialize\Serializer\Json;
use MageOS\AdminAssistant\Api\BotInterface;
use MageOS\AdminAssistant\Model\Http\Response\Stream;
use Psr\Log\LoggerInterface;
use MageOS\AdminAssistant\Api\AgentInterface;
use MageOS\AdminAssistant\Api\CallbackInterface;

/**
 * Index action.
 */
class Chat extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    protected $chat;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param MessageFactory $messageFactory
     * @param Json $serializer
     * @param BotInterface $bot
     * @param LoggerInterface $logger
     * @param Stream $stream
     * @param AgentInterface[] $agents
     * @param CallbackInterface[] $callbacks
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private MessageFactory $messageFactory,
        private Json $serializer,
        private BotInterface $bot,
        private LoggerInterface $logger,
        private Stream $stream,
        private array $agents = [],
        private array $callbacks = [],
    ) {
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

        $agentMatched = false;
        foreach ($this->agents as $agent) {
            if($agent->isEnabled() && $result = $agent->execute($lastSysMessage)) {
                $agentMatched = true;
                $this->stream->setData($result);
                // TODO: might worth to support chaining multiple agents
                break;
            }
        }
        if(!$agentMatched) {
            try {
                $llmAnswer = $this->bot->answer($messages);
                $this->stream->setData($llmAnswer);
                foreach ($this->callbacks as $callback) {
                    if($callback->isEnabled()) {
                        $this->stream->addCallback($callback);
                    }
                }
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
