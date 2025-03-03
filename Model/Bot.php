<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Model;

use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\VectorStores\OpenSearch\OpenSearchVectorStoreFactory;
use LLPhant\Query\SemanticSearch\QuestionAnsweringFactory;
use MageOS\AdminAssist\Api\BotInterface;
use OpenSearch\ClientBuilder;
use LLPhant\Embeddings\DataReader\FileDataReaderFactory;
use Psr\Http\Message\StreamInterface;

class Bot implements BotInterface
{
    public const INDEX_NAME = 'assist_knowledge_index';

    protected $chat;
    protected $vectorStore;
    protected $embeddingGenerator;
    protected $client;
    protected $systemMessage;
    public function __construct(
        private QuestionAnsweringFactory $questionAnsweringFactory,
        private OpenSearchVectorStoreFactory $openSearchVectorStoreFactory,
        private LlmFactory $llmFactory,
        private FileDataReaderFactory $fileDataReaderFactory,
    ){
        $this->chat = $this->llmFactory->createChat();
        $this->systemMessage = 'You are an assistant to guide the user through the process of managing a magento2 ecommerce store using the magento admin panel; User is already logged in admin panel; Keep the response simple short and clear; Ask user for more details or clarification before you are confident with the answer.';
        $this->chat->setSystemMessage($this->systemMessage);


        //TODO seperate elasticsearch dependency
        $this->client = $client = ClientBuilder::create()
            ->setHosts(['http://search:9200'])
            ->setRetries(2)
            ->build();
        $this->vectorStore = $this->openSearchVectorStoreFactory->create(['client' => $client, 'indexName' => self::INDEX_NAME]);;
        $this->embeddingGenerator = $this->llmFactory->createEmbedding();
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
        $qa->systemMessageTemplate = $this->systemMessage."\n\n{context}.";
        return $qa->answerQuestionFromChat($messages);
    }

    public function reset(): self
    {
        $this->client->indices()->delete([
            'index' => self::INDEX_NAME,
            'ignore_unavailable' => true
        ]);
        $this->vectorStore = $this->openSearchVectorStoreFactory->create(['client' => $this->client, 'indexName' => self::INDEX_NAME]);;
        return $this;
    }

    public function learn($docPath): self
    {
        $reader = $this->fileDataReaderFactory->create(['filePath' => $docPath]);
        $documents = $reader->getDocuments();
        $splitDocuments = DocumentSplitter::splitDocuments($documents, 800);
        $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);
        $embeddedDocuments = $this->embeddingGenerator->embedDocuments($formattedDocuments);

        $this->vectorStore->addDocuments($embeddedDocuments);

        return $this;
    }
}
