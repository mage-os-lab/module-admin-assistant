<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\Product;

class Config
{
    public const XML_PATH_ENABLED = 'admin/aiassistant/enabled';
    public const XML_PATH_PROVIDER = 'admin/aiassistant/provider';
    public const XML_PATH_HOST = 'admin/aiassistant/url';
    public const XML_PATH_MODEL = 'admin/aiassistant/model';
    public const XML_PATH_API_KEY = 'admin/aiassistant/api_key';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED
        );
    }
    public function getProvider(): string
    {
        return (string)$this->scopeConfig->isSetFlag(
            self::XML_PATH_PROVIDER
        );
    }

    public function getApiKey(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_API_KEY
        );
    }

    public function getHost(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_HOST
        );
    }

    public function getModel(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_MODEL
        );
    }
}
