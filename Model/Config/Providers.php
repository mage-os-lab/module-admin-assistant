<?php
declare(strict_types=1);

namespace MageOS\AdminAssist\Model\Config;

/**
 * @api
 * @since 100.0.2
 */
class Providers implements \Magento\Framework\Option\ArrayInterface
{
    //@todo: add OpenAI
    CONST OPENAI = 'openai';
    CONST OLLAMA = 'ollama';
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        foreach ($this->toArray() as $key => $value) {
            $options[] = ['value' => $key, 'label' => $value];
        }
        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            Providers::OLLAMA => __('Ollama'),
            //Providers::OPENAI => __('OpenAI')
        ];
    }
}
