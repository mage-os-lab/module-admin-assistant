<?php

namespace MageOS\AdminAssistant\Model\Callback;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use MageOS\AdminAssistant\Api\BotInterface;
use MageOS\AdminAssistant\Api\CallbackInterface;
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
        private VectorStore $vectorStore,
        private UrlInterface $urlBuilder,
        private Escaper $escaper,
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

        //TODO: use a new bot instance with custom system prompt
        $abstractedMessage = $this->getBot()->generate('You are a text analyzer, given a message, if the message is about guiding user through system menu navigation, then simply return the possible url path, topic and goal; if the message is not about navigating system menu, then simply return no. The message: ' . $data);
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
        $this->vectorStore->delete(self::INDEX);
        $vectorStore = $this->vectorStore->load(self::INDEX);
        //todo config for path
        $vectorStore->addDocuments('/var/www/pub/media/docs/pages');
    }

    public function getLink(string $message): array
    {
        $link = [];
        $vectorStore = $this->vectorStore->load(self::INDEX);
        //assemble target message with last set of Q&A
//        $message = '';
//        if($answer = array_pop($messages)) {
//            $message .= $answer->content;
//        }
//        if($question = array_pop($messages)) {
//            $message .= $question->content;
//        }
        if($message) {
            $documents = $vectorStore->match($message);
            if($documents) {
                $possibleLinkDoc = explode("\n",$documents[0]->content);
                $link = [
                    'url' => $this->urlBuilder->getUrl($possibleLinkDoc[0]),
                    'title' => $possibleLinkDoc[1],
                ];
            }
        }
        return $link;
    }
}
