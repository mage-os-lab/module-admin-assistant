<?php

namespace MageOS\AdminAssistant\Model\Agent;

use LLPhant\Chat\Enums\ChatRole;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use MageOS\AdminAssistant\Api\AgentInterface;
use MageOS\AdminAssistant\Api\BotInterface;

class Sql implements AgentInterface
{
    public const CODE = 'sql';
    protected $sqlRetry = 0;
    protected $bot;

    public function __construct(
        private readonly \Magento\Framework\App\ResourceConnection $resourceConnection,
        private readonly \MageOS\AdminAssistant\Model\TextTableFactory $textTableFactory,
        private readonly \LLPhant\Chat\MessageFactory $messageFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {}

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('admin/aiassistant/agent_sql');
    }

    public function setBot($bot): void
    {
        $this->bot = $bot;
    }

    public function getBot(): BotInterface
    {
        return $this->bot;
    }

    public function execute(array $messages): array
    {
        $result = [];
        $lastSysMessage = '';
        foreach ($messages as $message) {
            if($message->role == ChatRole::Assistant) {
                $lastSysMessage = $message->content;
            }
        }

        preg_match_all('/```sql(.*?)```/s', $lastSysMessage, $matches);
        $sql = '';
        if(!empty($matches[1][0])) {
            $sql = $matches[1][0];
        }

        $limit = $this->scopeConfig->getValue('admin/aiassistant/agent_sql_limit');
        if($limit && !stristr($sql, ' limit ')) {
            $sql .= ' limit ' . $limit;
        }
        if($this->sqlRetry++ > 3) {
            $result['error'] = 'Query Failed!';
        }
        elseif($sql) {
            $safeguard = $this->messageFactory->create();
            $safeguard->role = ChatRole::from('user');
            $safeguard->content = '```sql ' . $sql . ' ``` Is the above mysql query safe to execute and will not modify data or leak critical system, personal or financial information? Just answer yes or no.';
            $answer = (string)$this->getBot()->answer([$safeguard]);
            $this->logger->debug('SQL Safeguard answer: ' . $answer);
            if(!stristr($answer, 'yes')) {
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
                    $this->logSql($sql);
                }
                catch(\Exception $e) {
                    $this->logger->info($e->getMessage());
                    // TODO: no need for question answering, a chat method is good enough
                    $autofix = $this->messageFactory->create();
                    $autofix->role = ChatRole::from('user');
                    $autofix->content = '```sql ' . $sql . ' ``` The above mysql query failed with this error message: ' . $e->getMessage() . ' from the server; Please correct the query, no confirmation needed';
                    $answer = (string)$this->getBot()->answer([$autofix]);
                    $result = $this->execute($answer);
                }
            }

        }
        return $result;
    }

    public function logSql($sql): void
    {
        if($this->scopeConfig->isSetFlag('admin/aiassistant/agent_sql_log')) {
            $this->logger->info($sql);
        }
        $this->logger->info('SQL query executed');
    }
}
