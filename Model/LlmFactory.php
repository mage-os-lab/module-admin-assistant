<?php
declare(strict_types=1);

namespace MageOS\AdminAssistant\Model;

use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\OllamaChatFactory;
use LLPhant\Chat\OpenAIChatFactory;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGeneratorFactory;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGeneratorFactory;
use LLPhant\OllamaConfig;
use LLPhant\OpenAIConfig;
use Magento\Framework\Exception\InvalidArgumentException;
use MageOS\AdminAssistant\Model\Config;
use MageOS\AdminAssistant\Model\Config\Providers;

class LlmFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly Providers $providers,
        private OllamaConfig $ollamaConfig,
        private OllamaChatFactory $ollamaChatFactory,
        private OpenAIConfig $openAIConfig,
        private OpenAIChatFactory $openAIChatFactory,
        private OllamaEmbeddingGeneratorFactory $ollamaEmbeddingGeneratorFactory,
        private OpenAI3SmallEmbeddingGeneratorFactory $openaiEmbeddingGeneratorFactory,
    ) {}

    public function createChat(): ChatInterface
    {
        return $this->create('chat');
    }

    public function createEmbedding(): EmbeddingGeneratorInterface
    {
        return $this->create('embedding');
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function create(string $type): ChatInterface|EmbeddingGeneratorInterface
    {
        $chat = null;
        $embedding = null;
        $provider = $this->config->getProvider();
        if ($provider === Providers::OLLAMA) {
            $this->ollamaConfig->model = $this->config->getModel();
            $this->ollamaConfig->url = $this->config->getHost();
            $chat = $this->ollamaChatFactory->create(['config' => $this->ollamaConfig]);
            $embedding = $this->ollamaEmbeddingGeneratorFactory->create(['config' => $this->ollamaConfig]);
        }
        elseif ($provider === Providers::OPENAI) {
            $this->openAIConfig->model = $this->config->getModel();
            $this->openAIConfig->apiKey = $this->config->getApiKey();
            if($host = $this->config->getHost()) {
                $this->openAIConfig->url = $host;
            }
            $chat = $this->openAIChatFactory->create(['config' => $this->openAIConfig]);
            $embedding = $this->openaiEmbeddingGeneratorFactory->create(['config' => $this->openAIConfig]);
        }
        else {
            throw new InvalidArgumentException(__('%s is not supported', $provider));
        }
        return match($type) {
            'chat' => $chat,
            'embedding' => $embedding,
            default => throw new InvalidArgumentException(__('Invalid type')),
        };
    }
}
