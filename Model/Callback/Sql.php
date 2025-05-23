<?php

namespace MageOS\AdminAssistant\Model\Callback;

use MageOS\AdminAssistant\Api\BotInterface;
use MageOS\AdminAssistant\Api\CallbackInterface;

class Sql implements CallbackInterface
{
    protected $bot;
    public function __construct(
        private \MageOS\AdminAssistant\Model\Agent\Sql $agent
    ) {}

    public function isEnabled(): bool
    {
        return $this->agent->isEnabled();
    }

    public function execute(string $data): array
    {
        $result = [];
        if(preg_match('/```sql(.*?)```/s', $data)) {
            // @TODO: translate
            $result = ['html' => '<button class="deep-chat-button deep-chat-suggestion-button" style="border: 1px solid green">Run Query</button>'];
        }
        return $result;
    }

    public function setBot($bot): void
    {
        $this->bot = $bot;
    }

    public function getBot(): BotInterface
    {
        return $this->bot;
    }

    public function learn(): void
    {
        // TODO: Implement learn() method.
        return;
    }
}
