<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\AnalysisService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-analysis-backfill',
    description: 'Backfills end-of-month financial analyses for all historical (already-closed) months a user has transaction data for.',
)]
class GenerateAnalysisBackfillCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly AnalysisService $analysisService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'user',
            null,
            InputOption::VALUE_REQUIRED,
            'Restrict backfill to a single user, identified by email (for safe manual testing before running against everyone)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailFilter = $input->getOption('user');

        if (null !== $emailFilter) {
            $user = $this->userRepository->findOneBy(['email' => $emailFilter]);
            if (!$user) {
                $io->error(\sprintf('No user found with email "%s".', $emailFilter));

                return Command::FAILURE;
            }
            $users = [$user];
        } else {
            $users = $this->userRepository->findAll();
        }

        $io->info(\sprintf('Backfilling end-of-month analyses for %d user(s)...', \count($users)));

        $currentMonth = (new \DateTime())->format('Y-m');
        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            $months = $this->transactionRepository->getDistinctMonthsBefore($user, $currentMonth);

            if ([] === $months) {
                $io->writeln(\sprintf('  - User %s — no historical months with data', $user->getEmail()));
                continue;
            }

            foreach ($months as $period) {
                try {
                    $analysis = $this->analysisService->generateForPeriod($user, $period, 'end');
                } catch (\Throwable $e) {
                    ++$errors;
                    $io->writeln(\sprintf('  ✗ User %s — %s — error: %s', $user->getEmail(), $period, $e->getMessage()));
                    continue;
                }

                if ($analysis) {
                    ++$generated;
                    $io->writeln(\sprintf('  ✓ User %s — %s — analysis generated (id: %d)', $user->getEmail(), $period, $analysis->getId()));
                } else {
                    ++$skipped;
                    $io->writeln(\sprintf('  - User %s — %s — skipped (already exists or LLM error)', $user->getEmail(), $period));
                }
            }
        }

        $io->success(\sprintf('Done. Generated: %d, Skipped: %d, Errors: %d', $generated, $skipped, $errors));

        return Command::SUCCESS;
    }
}
