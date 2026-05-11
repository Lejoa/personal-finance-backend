<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\AnalysisService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-analysis',
    description: 'Generates a pedagogical financial analysis for all users at a given checkpoint (mid|end).',
)]
class GenerateMonthlyAnalysisCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AnalysisService $analysisService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'checkpoint',
            null,
            InputOption::VALUE_REQUIRED,
            'Checkpoint of the month: "mid" (day 15) or "end" (day 30)',
            'mid'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $checkpoint = $input->getOption('checkpoint');

        if (!\in_array($checkpoint, ['mid', 'end'], true)) {
            $io->error('Invalid checkpoint. Use "mid" or "end".');

            return Command::FAILURE;
        }

        $users = $this->userRepository->findAll();
        $io->info(\sprintf('Generating %s-month analyses for %d users...', $checkpoint, \count($users)));

        $generated = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $analysis = $this->analysisService->generateForUser($user, $checkpoint);

            if ($analysis) {
                ++$generated;
                $io->writeln(\sprintf('  ✓ User %s — analysis generated (id: %d)', $user->getEmail(), $analysis->getId()));
            } else {
                ++$skipped;
                $io->writeln(\sprintf('  - User %s — skipped (already exists or LLM error)', $user->getEmail()));
            }
        }

        $io->success(\sprintf('Done. Generated: %d, Skipped: %d', $generated, $skipped));

        return Command::SUCCESS;
    }
}
