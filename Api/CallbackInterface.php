<?php

namespace MageOS\AdminAssistant\Api;

interface CallbackInterface
{
    public function execute(string $message): array;
    public function isEnabled(): bool;
    public function setBot(BotInterface $bot): void;
    public function getBot(): BotInterface;
}
