<?php
namespace MageOS\AdminAssistant\Block;

class Config extends \Magento\Backend\Block\Template
{
    public function getEndpoint()
    {
        return $this->getUrl('assistant/ai/chat');
    }

    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
