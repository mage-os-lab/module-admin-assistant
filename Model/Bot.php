<?php
declare(strict_types=1);

namespace MageOS\AdminAssistant\Model;

use LLPhant\Query\SemanticSearch\QuestionAnsweringFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Dir;
use MageOS\AdminAssistant\Api\BotInterface;
use MageOS\AdminAssistant\Service\VectorStore;
use Psr\Http\Message\StreamInterface;

class Bot implements BotInterface
{
    public const INDEX_NAME = 'assist_knowledge_index';

    protected $chat;
    protected $embeddingGenerator;
    protected $client;
    protected $systemMessage;

    public function __construct(
        private QuestionAnsweringFactory $questionAnsweringFactory,
        private VectorStore $vectorStore,
        private LlmFactory $llmFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private Dir $moduleDir,
        private array $agents = [],
        private array $callbacks = [],
    ){
        $this->chat = $this->llmFactory->createChat();
        $this->systemMessage = 'You are an assistant to guide the user through the process of managing a magento2 ecommerce store using the magento admin panel; User is already logged in admin panel; Keep the response simple short and clear; Ask user for more details or clarification before you are confident with the answer.';
        //TODO: decouple - attach additional prompt with agents
        if($this->scopeConfig->isSetFlag('admin/aiassistant/agent_sql')) {
            $this->systemMessage .= 'Write a mysql query if the user question is data related, SQL is always preferred when applicable, no confirmation is needed.';
        }
        $this->chat->setSystemMessage($this->systemMessage);

        $this->vectorStore->load(self::INDEX_NAME);
        $this->embeddingGenerator = $this->llmFactory->createEmbedding();

        foreach ($this->agents as $agent) {
            $agent->setBot($this);
        }
        foreach ($this->callbacks as $callback) {
            $callback->setBot($this);
        }
    }

    public function getAgents(): array
    {
        return $this->agents;
    }

    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    public function generate(String $message): String
    {
        return $this->chat->generateText($message);
    }

    public function answer($messages): StreamInterface
    {
        $qa = $this->questionAnsweringFactory->create([
            'vectorStoreBase' => $this->vectorStore->getEngine(),
            'embeddingGenerator' => $this->embeddingGenerator,
            'chat' => $this->chat,
        ]);
        $qa->systemMessageTemplate = $this->systemMessage."\n\n{context}. /no_think";
        return $qa->answerQuestionFromChat($messages);
    }

    public function reset(): self
    {
        $this->vectorStore->delete(self::INDEX_NAME);
        $this->vectorStore->load(self::INDEX_NAME);
        return $this;
    }

    public function learn(): self
    {
        $docPath = $this->moduleDir->getDir('MageOS_AdminAssistant', 'etc').'/../docs/manual';
        //todo config for path
        $this->vectorStore->addDocuments($docPath);

        foreach ($this->getCallbacks() as $callback) {
            $callback->learn();
        }

        return $this;
    }
}
