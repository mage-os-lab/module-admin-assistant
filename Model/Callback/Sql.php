<?php

namespace MageOS\AdminAssistant\Model\Callback;

use MageOS\AdminAssistant\Model\Http\Response\Stream\CallbackInterface;

class Sql implements CallbackInterface
{

    public function execute(string $data): array
    {
        $result = [];
        if(preg_match('/```sql(.*?)```/s', $data)) {
            // @TODO: translate
            $result = ['html' => '<div class="deep-chat-temporary-message"><button class="deep-chat-button deep-chat-suggestion-button" style="border: 1px solid green">Run Query</button></div>'];
        }
        return $result;
    }
}
