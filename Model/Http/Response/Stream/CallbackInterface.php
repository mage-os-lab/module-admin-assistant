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
}
