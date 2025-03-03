<?php
declare(strict_types=1);

namespace MageOS\AdminAssistant\Api;

use Psr\Http\Message\StreamInterface;

interface BotInterface
{
    /**
     * Generate a response to a message
     *
     * @param string $message
     * @return string
     */
    public function generate(String $message): String;

    /**
     * Answer a message
     *
     * @param string $messages
     * @return StreamInterface
     */
    public function answer($messages): StreamInterface;

    /**
     * Forget all learned documents
     *
     * @return self
     */
    public function reset(): self;

    /**
     * Learn from documents
     *
     * @param $docPath
     * @return self
     */
    public function learn($docPath): self;
}
