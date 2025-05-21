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
     * @param array $messages
     * @return StreamInterface
     */
    public function answer(array $messages): StreamInterface;

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
    public function learn(): self;

    /**
     * Get agents to process user request
     * @return array
     */
    public function getAgents(): array;

    /**
     * Get callbacks to react on the llm response
     * @return array
     */
    public function getCallbacks(): array;
}
