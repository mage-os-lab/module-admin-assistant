<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Api;

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
     * Learn from documents
     *
     * @param $documents
     * @param bool $forgetOldKnowledge
     * @return self
     */
    public function learn($documents, $forgetOldKnowledge = true): self;
}
