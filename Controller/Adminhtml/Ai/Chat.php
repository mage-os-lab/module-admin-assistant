<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MageOS\AdminAssist\Controller\Adminhtml\Ai;

use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\MessageFactory;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGeneratorFactory;
use LLPhant\Embeddings\VectorStores\OpenSearch\OpenSearchVectorStoreFactory;
use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChatFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use \Magento\Framework\Serialize\Serializer\Json;
use LLPhant\Query\SemanticSearch\QuestionAnsweringFactory;
use MageOS\AdminAssist\Model\Bot;
use OpenSearch\ClientBuilder;
use OpenSearch\Common\Exceptions\OpenSearchException;
use MageOS\AdminAssist\Model\TextTableFactory;
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
    protected $embeddingGenerator;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $answerFactory,
        OllamaConfig $ollamaConfig,
        private OllamaChatFactory $ollamaChatFactory,
        private QuestionAnsweringFactory $questionAnsweringFactory,
        private OpenSearchVectorStoreFactory $openSearchVectorStoreFactory,
        private OllamaEmbeddingGeneratorFactory $ollamaEmbeddingGeneratorFactory,
        private MessageFactory $messageFactory,
        private Json $serializer,
        private ResourceConnection $resourceConnection,
        private TextTableFactory $textTableFactory,
        private Bot $bot,
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
