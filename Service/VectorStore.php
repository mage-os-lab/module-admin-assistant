<?php
declare(strict_types=1);

namespace MageOS\AdminAssistant\Service;

use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\VectorStores\OpenSearch\OpenSearchVectorStoreFactory;
use LLPhant\Embeddings\DataReader\FileDataReaderFactory;
use LLPhant\Embeddings\EmbeddingGenerator;
use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Exception;
use MageOS\AdminAssistant\Model\LlmFactory;
use LLPhant\Embeddings\VectorStores\OpenSearch\OpenSearchVectorStore;

class VectorStore
{
    private $client;
    private $vectorStoreFactory;
    private $embeddingGenerator;
    private $fileDataReaderFactory;

    private OpenSearchVectorStore $engine;

    public function __construct(
        OpenSearchVectorStoreFactory $vectorStoreFactory,
        private LlmFactory $llmFactory,
        FileDataReaderFactory $fileDataReaderFactory,
        ClientResolver $clientResolver,
        ScopeConfigInterface $scopeConfig
    ) {
        $search = $clientResolver->create();
        if (!($search instanceof \Magento\OpenSearch\Model\OpenSearch)) {
            throw new Exception('OpenSearch is not available');
        }
        $this->client = $search->getOpenSearchClient();
        $this->vectorStoreFactory = $vectorStoreFactory;
        $this->embeddingGenerator = $this->llmFactory->createEmbedding();
        $this->fileDataReaderFactory = $fileDataReaderFactory;
    }

    public function delete(string $indexName) {
        $this->client->indices()->delete([
            'index' => $indexName,
            'ignore_unavailable' => true
        ]);
    }

    public function load(string $indexName): self {
        $this->engine = $this->vectorStoreFactory->create(['client' => $this->client, 'indexName' => $indexName]);
        return $this;
    }

    public function getEngine(): OpenSearchVectorStore
    {
        return $this->engine;
    }

    /**
     * Load documents from a folder, Embed, and add to the current vector store index
     * TODO: decouple embedding
     */
    public function addDocuments(string $docPath) {
        $reader = $this->fileDataReaderFactory->create(['filePath' => $docPath]);
        $documents = $reader->getDocuments();
        $splitDocuments = DocumentSplitter::splitDocuments($documents, 800);
        $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);
        $embeddedDocuments = $this->embeddingGenerator->embedDocuments($formattedDocuments);

        $this->engine->addDocuments($embeddedDocuments);
    }

    public function match(string $message, int $k = 1): array
    {
        $embeddedMessage = $this->embeddingGenerator->embedText($message);
        return $this->engine->similaritySearch($embeddedMessage, $k);
    }
}
