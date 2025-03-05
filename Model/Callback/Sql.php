<?php

namespace MageOS\AdminAssistant\Model\Callback;

use MageOS\AdminAssistant\Model\Http\Response\Stream\CallbackInterface;

class Sql implements CallbackInterface
{

    public function execute(string $data): array
    {
        if(preg_match('/```sql(.*?)```/s', $data)) {
            // @TODO: translate
            return ['text' => 'Run Query'];
        }
    }
}
