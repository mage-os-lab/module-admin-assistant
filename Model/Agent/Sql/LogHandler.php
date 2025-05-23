<?php
namespace MageOS\AdminAssistant\Model\Agent\Sql;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Monolog\LogRecord;

class LogHandler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/assistant_sql_audit.log';

    /**
     * @var int
     */
    protected $loggerType = \Monolog\Logger::INFO;

    /**
     * @param DriverInterface $filesystem
     * @param \Monolog\Formatter\LineFormatter $lineFormatter
     * @param ScopeConfigInterface $scopeConfig
     * @param string $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        \Monolog\Formatter\LineFormatter $lineFormatter,
        private readonly ScopeConfigInterface $scopeConfig,
        $filePath = null
    ) {
        parent::__construct($filesystem, $filePath);

        $this->setBubble(false);
        $this->setFormatter($lineFormatter);
    }

    public function isHandling(LogRecord $record): bool
    {
        if(!$this->scopeConfig->isSetFlag('admin/aiassistant/agent_sql_log')) {
            return false;
        }

        return parent::isHandling($record);
    }
}
