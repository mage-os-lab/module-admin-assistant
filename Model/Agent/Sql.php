<?php

namespace MageOS\AdminAssistant\Model\Agent;

use LLPhant\Chat\Enums\ChatRole;
use Psr\Log\LoggerInterface;
use MageOS\AdminAssistant\Api\AgentInterface;

class Sql implements AgentInterface
{
    protected $sqlRetry = 0;

    public function __construct(
        private readonly \Magento\Framework\App\ResourceConnection $resourceConnection,
        private readonly \MageOS\AdminAssistant\Model\TextTableFactory $textTableFactory,
        private readonly \LLPhant\Chat\MessageFactory $messageFactory,
        private readonly \MageOS\AdminAssistant\Model\Bot $bot,
        private readonly LoggerInterface $logger
    ) {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function execute(string $message): array
    {
        preg_match_all('/```sql(.*?)```/s', $message, $matches);
        $sql = '';
        if(!empty($matches[1][0])) {
            $sql = $matches[1][0];
        }
        $result = [];
        if($this->sqlRetry++ > 3) {
            $result['error'] = 'Query Failed!';
        }
        elseif($sql) {
            $safeguard = $this->messageFactory->create();
            $safeguard->role = ChatRole::from('user');
            $safeguard->content = '```sql ' . $sql . ' ``` Is the above mysql query safe to execute and will not modify data or leak critical system, personal or financial information? Just answer yes or no.';
            $answer = (string)$this->bot->answer([$safeguard]);
            if(stripos($answer, 'yes') !== 0) {
                $result['error'] = 'The query was not safe to run, please review the query and execute manually.';
            }
            else {
                $connection = $this->resourceConnection->getConnection();
                $connection->beginTransaction();
                try {
                    $result = $connection->fetchAll($sql);
                    $tt = $this->textTableFactory->create(['header' => null, 'content'=> $result]);
                    $result = [
                        'text' => $tt->render(),
                    ];
                }
                catch(\Exception $e) {
                    $this->logger->info($e->getMessage());
                    // TODO: no need for question answering, a chat method is good enough
                    $autofix = $this->messageFactory->create();
                    $autofix->role = ChatRole::from('user');
                    $autofix->content = '```sql ' . $sql . ' ``` The above mysql query failed with this error message: ' . $e->getMessage() . ' from the server; Please correct the query, no confirmation needed';
                    $answer = (string)$this->bot->answer([$autofix]);
                    $result = $this->execute($answer);
                }
            }

        }
        return $result;
    }
}
