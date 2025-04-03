<?php

namespace MageOS\AdminAssistant\Model\Http\Response\Stream;

interface CallbackInterface
{
    /**
     * Callback function
     *
     * @param string $data
     * @return array
     */
    public function execute(string $data): array;

    public function learn(): void;
    public function retrieve(string $message): string;
}
