<?php

namespace App\Command;

use App\Repository\EmbeddingRepository;
use App\Repository\TipRepository;
use App\Repository\TipReferenceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:index-tips',
    description: 'Generates and stores embeddings for all tips and their bibliographic references.',
)]
class IndexTipsCommand extends Command
{
    public function __construct(
        private readonly TipRepository $tipRepository,
        private readonly TipReferenceRepository $referenceRepository,
        private readonly EmbeddingRepository $embeddingRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly string $llmServiceUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Re-index all tips even if embeddings already exist (deletes existing embeddings first)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $tips = $this->tipRepository->findAll();
        $io->info(sprintf('Indexing %d tips...', count($tips)));

        $indexed = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($tips as $tip) {
            $existing = $this->embeddingRepository->findBy(['tip' => $tip]);

            if (!empty($existing) && !$force) {
                ++$skipped;
                $io->writeln(sprintf('  - Tip #%d "%s" — skipped (already indexed)', $tip->getId(), $tip->getTitle()));
                continue;
            }

            if (!empty($existing) && $force) {
                $this->embeddingRepository->deleteByTip($tip);
            }

            try {
                // Embed the tip itself: title + description
                $tipText = $tip->getTitle() . '. ' . $tip->getDescription();
                $vector  = $this->fetchEmbedding($tipText);
                $this->embeddingRepository->save($tip, null, $tipText, $vector);

                // Embed each bibliographic reference's excerpt
                $references = $this->referenceRepository->findByTip($tip);
                foreach ($references as $reference) {
                    $refVector = $this->fetchEmbedding($reference->getExcerpt());
                    $this->embeddingRepository->save($tip, $reference, $reference->getExcerpt(), $refVector);
                }

                ++$indexed;
                $io->writeln(sprintf(
                    '  ✓ Tip #%d "%s" — indexed (%d reference(s))',
                    $tip->getId(),
                    $tip->getTitle(),
                    count($references),
                ));
            } catch (\Exception $e) {
                ++$errors;
                $io->writeln(sprintf(
                    '  ✗ Tip #%d "%s" — error: %s',
                    $tip->getId(),
                    $tip->getTitle(),
                    $e->getMessage(),
                ));
            }
        }

        $io->success(sprintf('Done. Indexed: %d, Skipped: %d, Errors: %d', $indexed, $skipped, $errors));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Calls POST /llm/embed on the LLM service and returns the float vector.
     *
     * @return float[]
     */
    private function fetchEmbedding(string $text): array
    {
        $response = $this->httpClient->request('POST', $this->llmServiceUrl . '/llm/embed', [
            'json'         => ['text' => $text],
            'timeout'      => 30,
            'max_duration' => 30,
        ]);

        $data = $response->toArray();

        if (!isset($data['embedding']) || !is_array($data['embedding'])) {
            throw new \RuntimeException('Invalid embedding response from LLM service');
        }

        return $data['embedding'];
    }
}
