<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MageOS\AdminAssist\Controller\Adminhtml\Ai;

use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChatFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Index action.
 */
class Index extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    protected $chat;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        OllamaConfig $ollamaConfig,
        private OllamaChatFactory $ollamaChatFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $ollamaConfig->model = 'llama3.2';
        $ollamaConfig->url = "http://host.docker.internal:11434/api/";
        $this->chat = $this->ollamaChatFactory->create([$ollamaConfig]);
        parent::__construct($context);
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MageOS_Assistant::Ai');
        $resultPage->addBreadcrumb(__('Assistant'), __('Assistant'));
        $resultPage->getConfig()->getTitle()->prepend(__('MageOS AI Assistant'));

        return $resultPage;
    }
}
