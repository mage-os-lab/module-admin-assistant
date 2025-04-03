<?php

namespace MageOS\AdminAssistant\Api;

interface AgentInterface
{
    public const CODE = '';
    public function execute(array $messages): array;
    public function isEnabled(): bool;
    public function setBot(BotInterface $bot): void;
    public function getBot(): BotInterface;
}
