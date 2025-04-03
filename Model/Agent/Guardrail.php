<?php

namespace MageOS\AdminAssistant\Model\Agent;

use LLPhant\Chat\Enums\ChatRole;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use MageOS\AdminAssistant\Api\AgentInterface;
use LLPhant\Chat\Message;
use MageOS\AdminAssistant\Api\BotInterface;

class Guardrail implements AgentInterface
{
    public const CODE = 'guardrail';
    protected $sqlRetry = 0;

    protected $bot;

    public function __construct(
        private readonly \LLPhant\Chat\MessageFactory $messageFactory,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {}

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('admin/aiassistant/agent_guardrail');
    }

    public function setBot($bot): void
    {
        $this->bot = $bot;
    }

    public function getBot(): BotInterface
    {
        return $this->bot;
    }

    public function execute(array $messages): array
    {
        $result = [];
        $lastUserMessage = '';
        foreach ($messages as $message) {
            if($message->role == ChatRole::User) {
                $lastUserMessage = $message->content;
            }
        }
        $system = $this->messageFactory->create();
        $system->role = ChatRole::from('assistant');
        $system->content = $this->scopeConfig->getValue('admin/aiassistant/agent_guardrail_prompt');
        $user = $this->messageFactory->create();
        $user->role = ChatRole::from('user');
        $user->content = $lastUserMessage;

        $answer = (string)$this->getBot()->answer([$system, $user]);
        if(stripos($answer, 'allowed') !== 0) {
            $result['error'] = 'I can only talk about magento';
        }
        return $result;
    }
}
