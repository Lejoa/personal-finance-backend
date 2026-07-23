<?php

namespace App\Tests\Unit\Command;

use App\Command\GenerateAnalysisBackfillCommand;
use App\Entity\FinancialAnalysis;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\AnalysisService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateAnalysisBackfillCommandTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private TransactionRepository&MockObject $transactionRepository;
    private AnalysisService&MockObject $analysisService;
    private GenerateAnalysisBackfillCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->analysisService = $this->createMock(AnalysisService::class);

        $this->command = new GenerateAnalysisBackfillCommand(
            $this->userRepository,
            $this->transactionRepository,
            $this->analysisService
        );

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteGeneratesForAllUsersAcrossTheirHistoricalMonths(): void
    {
        $user1 = $this->makeUser(1);
        $user2 = $this->makeUser(2);

        $this->userRepository->method('findAll')->willReturn([$user1, $user2]);
        $this->transactionRepository->method('getDistinctMonthsBefore')->willReturn(['2025-01', '2025-02']);
        $this->analysisService->method('generateForPeriod')->willReturn($this->makeAnalysis());

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Generated: 4, Skipped: 0, Errors: 0', $this->commandTester->getDisplay());
    }

    public function testExecuteSkipsUserWithNoHistoricalMonths(): void
    {
        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);
        $this->transactionRepository->method('getDistinctMonthsBefore')->willReturn([]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('no historical months with data', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Generated: 0, Skipped: 0, Errors: 0', $this->commandTester->getDisplay());
    }

    public function testExecuteSkipsMonthWhenServiceReturnsNull(): void
    {
        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);
        $this->transactionRepository->method('getDistinctMonthsBefore')->willReturn(['2025-01']);
        $this->analysisService->method('generateForPeriod')->willReturn(null);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Generated: 0, Skipped: 1, Errors: 0', $this->commandTester->getDisplay());
    }

    public function testExecuteCountsErrorsAndContinuesLoop(): void
    {
        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);
        $this->transactionRepository->method('getDistinctMonthsBefore')->willReturn(['2025-01', '2025-02']);

        $callCount = 0;
        $this->analysisService->method('generateForPeriod')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;
            if (1 === $callCount) {
                throw new \RuntimeException('LLM timeout');
            }

            return $this->makeAnalysis();
        });

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Errors: 1', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Generated: 1', $this->commandTester->getDisplay());
        $this->assertStringContainsString('LLM timeout', $this->commandTester->getDisplay());
    }

    public function testExecuteWithUserOptionFiltersToSingleUser(): void
    {
        $user = $this->makeUser(1, 'x@example.com');
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'x@example.com'])
            ->willReturn($user);
        $this->userRepository->expects($this->never())->method('findAll');
        $this->transactionRepository->method('getDistinctMonthsBefore')->willReturn([]);

        $exitCode = $this->commandTester->execute(['--user' => 'x@example.com']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithUnknownUserEmailReturnsFailure(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $exitCode = $this->commandTester->execute(['--user' => 'ghost@example.com']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('ghost@example.com', $this->commandTester->getDisplay());
    }

    public function testExecuteCallsGenerateForPeriodWithEndCheckpointOnly(): void
    {
        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);
        $this->transactionRepository->method('getDistinctMonthsBefore')->willReturn(['2025-01']);

        $this->analysisService->expects($this->once())
            ->method('generateForPeriod')
            ->with($user, '2025-01', 'end')
            ->willReturn($this->makeAnalysis());

        $this->commandTester->execute([]);
    }

    public function testExecutePassesCurrentMonthAsExclusiveCutoffToRepository(): void
    {
        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);

        $this->transactionRepository->expects($this->once())
            ->method('getDistinctMonthsBefore')
            ->with($user, (new \DateTime())->format('Y-m'))
            ->willReturn([]);

        $this->commandTester->execute([]);
    }

    private function makeUser(int $id, ?string $email = null): User
    {
        $user = new User();
        $user->setEmail($email ?? "user{$id}@example.com");
        $user->setName("User {$id}");

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    private function makeAnalysis(int $id = 1): FinancialAnalysis
    {
        $analysis = new FinancialAnalysis();
        $analysis->setPeriod('2025-01');
        $analysis->setCheckpoint('end');
        $analysis->setContent('Analysis content');

        $ref = new \ReflectionProperty(FinancialAnalysis::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($analysis, $id);

        return $analysis;
    }
}
