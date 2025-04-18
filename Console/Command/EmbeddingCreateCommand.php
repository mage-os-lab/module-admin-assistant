<?php
declare(strict_types=1);

namespace MageOS\AdminAssistant\Console\Command;

use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use Magento\Framework\Exception\LocalizedException;
use MageOS\AdminAssistant\Api\BotInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Console\Cli;
use LLPhant\Embeddings\DataReader\FileDataReaderFactory;

/**
 * EmbeddingCreateCommand generates documentation embeddings
 */
class EmbeddingCreateCommand extends Command
{

    public function __construct(
        private FileDataReaderFactory $fileDataReaderFactory,
        private BotInterface $bot,
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('assistant:train')
            ->setDescription('Creates document embedding for AI assistant prompt engineering');

        parent::configure();
    }

    /**
     * Executes "aiassistant:embed" command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Embedding catalog documents</info>');

            $docPath = '/var/www/pub/media/docs/merchant/src/test';
            $output->writeln("<info>Embedding $docPath</info>");
            $this->bot->reset();
            $this->bot->learn($docPath);
            $output->writeln("<info>Documents Embedded</info>");
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Documents have been embedded and saved</info>');

        return Cli::RETURN_SUCCESS;
    }
}
