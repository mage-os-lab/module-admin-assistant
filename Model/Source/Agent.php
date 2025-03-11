<?php
namespace MageOS\AdminAssistant\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\AdminAssistant\Model\Agent\Guardrail;
use MageOS\AdminAssistant\Model\Agent\Sql;

class Agent implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => Guardrail::CODE, 'label' => __('Guardrail')],
            ['value' => Sql::CODE, 'label' => __('Run SQL query')],
        ];
    }
}
