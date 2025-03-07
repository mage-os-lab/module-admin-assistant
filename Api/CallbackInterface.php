<?php

namespace MageOS\AdminAssistant\Api;

interface CallbackInterface
{
    public function execute(string $message): array;
    public function isEnabled(): bool;
}
