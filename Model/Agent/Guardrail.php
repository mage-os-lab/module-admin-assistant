<?php

namespace MageOS\AdminAssistant\Model\Agent;

use LLPhant\Chat\Enums\ChatRole;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use MageOS\AdminAssistant\Api\AgentInterface;
use LLPhant\Chat\Message;

class Guardrail implements AgentInterface
{
    public const CODE = 'guardrail';
    protected $sqlRetry = 0;

    public function __construct(
        private readonly \LLPhant\Chat\MessageFactory $messageFactory,
        private readonly \MageOS\AdminAssistant\Model\Bot $bot,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {}

    public function isEnabled(): bool
    {
        return in_array(self::CODE, explode(',', $this->scopeConfig->getValue('admin/aiassistant/agents') ?? ''));
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
        $system->content = "Your role is to assess whether the user question is allowed or not. The allowed topics are magento store management and casual topic . If the topic is allowed, say 'allowed' otherwise say 'denied'";
        $user = $this->messageFactory->create();
        $user->role = ChatRole::from('user');
        $user->content = $lastUserMessage;

        $answer = (string)$this->bot->answer([$system, $user]);
        if(stripos($answer, 'allowed') !== 0) {
            $result['error'] = 'I can only talk about magento';
        }
        return $result;
    }
}
