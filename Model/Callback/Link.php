<?php

namespace MageOS\AdminAssistant\Model\Callback;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Module\Dir;
use MageOS\AdminAssistant\Api\BotInterface;
use MageOS\AdminAssistant\Api\CallbackInterface;
use MageOS\AdminAssistant\Model\LlmFactory;
use MageOS\AdminAssistant\Service\VectorStore;
use Psr\Log\LoggerInterface;

class Link implements CallbackInterface
{
    public const CODE = 'link';
    public const INDEX = 'assistant_kb_links';

    protected $bot;

    public function __construct(
        private readonly \Magento\Framework\App\ResourceConnection $resourceConnection,
        private readonly \MageOS\AdminAssistant\Model\TextTableFactory $textTableFactory,
        private readonly \LLPhant\Chat\MessageFactory $messageFactory,
        private LlmFactory $llmFactory,
        private VectorStore $vectorStore,
        private UrlInterface $urlBuilder,
        private Escaper $escaper,
        private Dir $moduleDir,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {}

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('admin/aiassistant/agent_link');
    }

    public function setBot($bot): void
    {
        $this->bot = $bot;
    }

    public function getBot(): BotInterface
    {
        return $this->bot;
    }

    public function execute(string $data): array
    {
        $result = [];

        $llmChat = $this->llmFactory->createChat();

        $llmChat->setSystemMessage('You are a text semantic analyzer. Given a message, if the message is generic and is not about navigating system menu and has no target page, simply return no; if the message is about helping user to navigation magento2 admin panel and has one specific target page, then simply return 2-3 keywords related to the user intention or goal. /no_think');

        //TODO use a new bot instance with custom system prompt
        $abstractedMessage = $llmChat->generateText($data);
        $this->logger->debug('Abstracted message: ' . $abstractedMessage);
        if(stripos($abstractedMessage, 'no') !== 0) {
            if($link = $this->getLink($abstractedMessage)) {
                $result = ['html' => '<div class="deep-chat-temporary-message"><a href="' . $link['url'] . '" class="deep-chat-button" style="border: 1px solid green">' .
                    $this->escaper->escapeHtml($link['title']) . '</a></div>'];
            }
        }

        return $result;
    }

    public function learn(): void
    {
        $docPath = $this->moduleDir->getDir('MageOS_AdminAssistant', 'etc').'/../docs/links';
        $this->vectorStore->delete(self::INDEX);
        $vectorStore = $this->vectorStore->load(self::INDEX);
        //todo config for path
        $vectorStore->addDocuments($docPath);
    }

    public function getLink(string $message): array
    {
        $link = [];
        $vectorStore = $this->vectorStore->load(self::INDEX);
        if($message) {
            $documents = $vectorStore->match($message);
            if($documents) {
                // knn + llm to get the best match, knn score along is often not enough
                $this->getBestMatch($documents, $message);
                if($docNum = $this->getBestMatch($documents, $message)) {
                    $document = $documents[$docNum - 1];
                    $possibleLinkDoc = explode("\n",$document->content);
                    $link = [
                        'url' => $this->urlBuilder->getUrl($possibleLinkDoc[0]),
                        'title' => $possibleLinkDoc[1],
                    ];
                }
            }
        }
        return $link;
    }

    protected function getBestMatch($documents, $message): int
    {
        $llmChat = $this->llmFactory->createChat();
        $llmChat->setSystemMessage('You are a text classifier, given a message and a collection of documents, if the message is closely related to one of the documents, return the document number; if there is no documents related, simply return 0. /no_think');
        //use llm to get the best single match out of the knn results
        $prompt = 'The message: ' . $message . '. The documents: ';
        $i = 1;
        foreach ($documents as $document) {
            $prompt .= 'document number ' . $i++ . ': '. $document->content . "\n";
        }
        $prompt .= " /n based on the above message and documents, please only return the numeric document number that is most closely related to the message. Don't return anything if no documents are related to the message.";
        $this->logger->debug('Suggested prompt: ' . $prompt);
        $respond = $llmChat->generateText($prompt);
        $docNum = 0;
        if(preg_match('/\d+/', $respond, $matches)) {
            $docNum = (int)$matches[0];
        }
        return $docNum;
    }
}
