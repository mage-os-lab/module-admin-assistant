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

    public const PATH_PROMPT_SYSTEM = 'admin/aiassistant/system_message';
    public const PATH_SQL_PROMPT_SYSTEM = 'admin/aiassistant/agent_sql_prompt_system';

    public const PATH_FLAG_AGENT_SQL = 'admin/aiassistant/agent_sql';

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
        $this->systemMessage = $this->scopeConfig->getValue(self::PATH_PROMPT_SYSTEM);
        //TODO: decouple - attach additional prompt with agents
        if($this->scopeConfig->isSetFlag(self::PATH_FLAG_AGENT_SQL)) {
            $this->systemMessage .= "\n\n" . $this->scopeConfig->getValue(self::PATH_SQL_PROMPT_SYSTEM);
        }
        $this->chat->setSystemMessage($this->systemMessage);

        $this->vectorStore->load(self::INDEX_NAME);
        $this->embeddingGenerator = $this->llmFactory->createEmbedding();

        //TODO: decouple - most of agents and callbacks do not require a bot model, a general LLM instance is sufficient
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
