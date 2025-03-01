<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Model;

use LLPhant\Chat\OllamaChatFactory;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGeneratorFactory;
use LLPhant\Embeddings\VectorStores\OpenSearch\OpenSearchVectorStoreFactory;
use LLPhant\OllamaConfig;
use LLPhant\Query\SemanticSearch\QuestionAnsweringFactory;
use MageOS\AdminAssist\Api\BotInterface;
use OpenSearch\ClientBuilder;
use Psr\Http\Message\StreamInterface;

class Bot implements BotInterface
{
    protected $chat;
    protected $vectorStore;
    protected $embeddingGenerator;
    public function __construct(
        OllamaConfig $ollamaConfig,
        private OllamaChatFactory $ollamaChatFactory,
        private QuestionAnsweringFactory $questionAnsweringFactory,
        private OpenSearchVectorStoreFactory $openSearchVectorStoreFactory,
        private OllamaEmbeddingGeneratorFactory $ollamaEmbeddingGeneratorFactory,
    ){
        $ollamaConfig->model = 'qwen2.5';
        $ollamaConfig->url = "http://host.docker.internal:11434/api/";
        $this->chat = $this->ollamaChatFactory->create([$ollamaConfig]);
        $this->chat->setSystemMessage('You are an assistant to guide the user through the process of managing a magento2 ecommerce store using the magento admin panel; User is already logged in admin panel; Keep the response simple short and clear; Ask user for more details or clarification before you are confident with the answer.');


        //TODO seperate elasticsearch dependency
        $client = ClientBuilder::create()
            ->setHosts(['http://search:9200'])
            ->setRetries(2)
            ->build();
        $this->vectorStore = $this->openSearchVectorStoreFactory->create(['client' => $client, 'indexName' => 'llphant_custom_index1']);;
        $this->embeddingGenerator = $this->ollamaEmbeddingGeneratorFactory->create(['config' => $ollamaConfig]);
    }

    public function generate(String $message): String
    {
        return $this->chat->generateText($message);
    }

    public function answer($messages): StreamInterface
    {
        $qa = $this->questionAnsweringFactory->create([
            'vectorStoreBase' => $this->vectorStore,
            'embeddingGenerator' => $this->embeddingGenerator,
            'chat' => $this->chat,
        ]);
        $qa->systemMessageTemplate = "You are an assistant to guide the user through the process of managing a magento2 ecommerce store using the magento admin panel; User is already logged in admin panel; Keep the response simple short and clear; Ask user for more details or clarification before you are confident with the answer.\n\n{context}.";
        return $qa->answerQuestionFromChat($messages);
    }
}
