<?php

namespace MageOS\AdminAssistant\Model\Agent;

class Sql
{
    protected $sqlRetry = 0;

    public function __construct(
        private readonly \Magento\Framework\App\ResourceConnection $resourceConnection,
        private readonly \MageOS\AdminAssistant\Model\TextTableFactory $textTableFactory,
        private readonly \MageOS\AdminAssistant\Model\Callback\MessageFactory $messageFactory,
        private readonly \MageOS\AdminAssistant\Model\Agent\Bot $bot
    ) {}

    public function execute(string $message): array
    {
        preg_match_all('/```sql(.*?)```/s', $message, $matches);
        $sql = '';
        if(!empty($matches[1][0])) {
            $sql = $matches[1][0];
        }
        $result = [];
        if($this->sqlRetry++ > 5) {
            $result['error'] = 'Query Failed!';
        }
        elseif($sql) {
            $connection = $this->resourceConnection->getConnection();
            try {
                $result = $connection->fetchAll($sql);
                $tt = $this->textTableFactory->create(['header' => null, 'content'=> $result]);
                $result = [
                    'text' => $tt->render(),
                ];
            }
            catch(\Exception $e) {
                // TODO: no need for question answering, a chat method is good enough
                $messages = [];
                $messages[] = $this->messageFactory->create(['role' => 'user', 'content' => '```sql' . $sql . '``` The above mysql query failed with this error message: ' . $e->getMessage() , ' from the server; Please correct the query']);
                $answer = (string)$this->bot->answer($messages);
                $result = $this->execute($answer);
            }
        }
        return $result;
    }
}
