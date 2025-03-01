<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Api;

interface BotInterface
{
    /**
     * @param String $message
     * @return String
     */
    public function generate(String $message): String;
}
