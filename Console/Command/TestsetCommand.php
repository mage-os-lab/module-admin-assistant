<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Console\Command;

use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\MessageFactory;
use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChatFactory;
use LLPhant\Query\SemanticSearch\QuestionAnsweringFactory;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use LLPhant\Embeddings\VectorStores\OpenSearch\OpenSearchVectorStoreFactory;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGeneratorFactory;
use OpenSearch\ClientBuilder;
use \Magento\Framework\Module\Dir;

/**
 * TestsetCommand generates a testset that you can load into an evaluation framework
 */
class TestsetCommand extends Command
{
    protected $chat;
    protected $embeddingGenerator;

    public function __construct(
        OllamaConfig $ollamaConfig,
        private OpenSearchVectorStoreFactory $openSearchVectorStoreFactory,
        private OllamaEmbeddingGeneratorFactory $embeddingGeneratorFactory,
        private OllamaChatFactory $ollamaChatFactory,
        private Dir $moduleDir,
        private MessageFactory $messageFactory,
        private QuestionAnsweringFactory $questionAnsweringFactory
    ) {
        $ollamaConfig->model = 'qwen2.5';
        $ollamaConfig->url = "http://host.docker.internal:11434/api/";
        $this->chat = $this->ollamaChatFactory->create([$ollamaConfig]);
        $this->embeddingGenerator = $this->embeddingGeneratorFactory->create(['config' => $ollamaConfig]);
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('aiassistant:testset')
            ->setDescription('Generates a test set that can be loaded into an evaluation framework');

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
            $testSet = explode("\n", file_get_contents($this->moduleDir->getDir('MageOS_AdminAssist', 'etc') . '/testset.jsonl'));
            $vectorStore = $this->openSearchVectorStoreFactory->create(['client' => $client, 'indexName' => 'llphant_custom_index1']);;


            $qa = $this->questionAnsweringFactory->create([
                'vectorStoreBase' => $vectorStore,
                'embeddingGenerator' => $this->embeddingGenerator,
                'chat' => $this->chat,
            ]);

            $qa->systemMessageTemplate = "You are an assistant to guide the user through the process of managing a magento2 ecommerce store using the magento admin panel; User is already logged in admin panel; Keep the response simple short and clear; Ask user for more details or clarification before you are confident with the answer.\n\n{context}.";

            foreach ($testSet as $i => $test) {
                $test = json_decode($test, true);
                if(!$test) {
                    $output->writeln("<error>Skipped question #{$i}</error>");
                    continue;
                }
                $message = $this->messageFactory->create();
                $message->role = ChatRole::from('user');
                $message->content = $test['user_input'];
                $messages[] = $message;
                $answer = (string)$qa->answerQuestionFromChat($messages);
                $test['response'] = $answer;
                $testSet[$i] = json_encode($test);
                file_put_contents($this->moduleDir->getDir('MageOS_AdminAssist', 'etc') . '/result.jsonl', json_encode($test) . PHP_EOL, FILE_APPEND);
            }

            //$output->writeln("<info>Documents Embedded</info>");
            //file_put_contents($this->moduleDir->getDir('MageOS_AdminAssist', 'etc') . 'testset.json', json_encode($testSet, JSON_PRETTY_PRINT));
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Test set is generated</info>');

        return Cli::RETURN_SUCCESS;
    }
}
