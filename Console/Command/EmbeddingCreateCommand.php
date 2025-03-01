<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Console\Command;

use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\OllamaConfig;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use LLPhant\Embeddings\DataReader\FileDataReaderFactory;
use LLPhant\Embeddings\VectorStores\OpenSearch\OpenSearchVectorStoreFactory;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGeneratorFactory;
use OpenSearch\ClientBuilder;

/**
 * EmbeddingCreateCommand generates documentation embeddings
 */
class EmbeddingCreateCommand extends Command
{

    public function __construct(
        private FileDataReaderFactory $fileDataReaderFactory,
        private OpenSearchVectorStoreFactory $openSearchVectorStoreFactory,
        private OllamaEmbeddingGeneratorFactory $embeddingGeneratorFactory,
        private OllamaConfig $ollamaConfig
    ) {
        $ollamaConfig->model = 'qwen2.5';
        $ollamaConfig->url = "http://host.docker.internal:11434/api/";
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('aiassistant:embed')
            ->setDescription('Creates document embedding for AI assistant prompt engineering');

        parent::configure();
    }

    /**
     * Executes "aiassistant:embed" command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {

            $client = ClientBuilder::create()
                ->setHosts(['http://search:9200'])
                ->setRetries(2)
                ->build();
            $client->indices()->delete([
                'index' => 'llphant_custom_index1',
                'ignore_unavailable' => true
            ]);
            $vectorStore = $this->openSearchVectorStoreFactory->create(['client' => $client, 'indexName' => 'llphant_custom_index1']);;

            $output->writeln('<info>Embedding catalog documents</info>');

            $docPath = '/var/www/pub/media/docs/merchant/src/test';
            $output->writeln("<info>Embedding $docPath</info>");
            $reader = $this->fileDataReaderFactory->create(['filePath' => $docPath]);
            $documents = $reader->getDocuments();
            $docsCount = count($documents);
            $output->writeln("<info>Retrieved {$docsCount} documents</info>");
            $splitDocuments = DocumentSplitter::splitDocuments($documents, 800);
            $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);
            $embeddingGenerator = $this->embeddingGeneratorFactory->create(['config' => $this->ollamaConfig]);
            $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);

            $output->writeln("<info>Documents Embedded</info>");

            $vectorStore->addDocuments($embeddedDocuments);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Documents have been embedded and saved</info>');

        return Cli::RETURN_SUCCESS;
    }
}
