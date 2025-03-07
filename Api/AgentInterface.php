<?php

namespace MageOS\AdminAssistant\Api;

interface AgentInterface
{
    public function execute(string $message): array;
    public function isEnabled(): bool;
}
