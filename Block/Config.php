<?php
namespace MageOS\AdminAssist\Block;

class Config extends \Magento\Backend\Block\Template
{
    public function getConfig()
    {
        return [
            'model' => 'llama3.2',
            'url' => 'http://host.docker.internal:11434/api/'
        ];
    }

    public function getEndpoint()
    {
        return $this->getUrl('assistant/ai/chat');
    }

    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
