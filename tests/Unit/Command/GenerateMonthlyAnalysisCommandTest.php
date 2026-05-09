<?php

namespace App\Tests\Unit\Command;

use App\Command\GenerateMonthlyAnalysisCommand;
use App\Entity\FinancialAnalysis;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AnalysisService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateMonthlyAnalysisCommandTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private AnalysisService&MockObject $analysisService;
    private GenerateMonthlyAnalysisCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->analysisService = $this->createMock(AnalysisService::class);

        $this->command = new GenerateMonthlyAnalysisCommand(
            $this->userRepository,
            $this->analysisService
        );

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteWithMidCheckpointGeneratesAnalysisForAllUsers(): void
    {
        $user1 = $this->makeUser(1);
        $user2 = $this->makeUser(2);

        $this->userRepository->method('findAll')->willReturn([$user1, $user2]);
        $this->analysisService->method('generateForUser')->willReturn($this->makeAnalysis());

        $exitCode = $this->commandTester->execute(['--checkpoint' => 'mid']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Generated: 2, Skipped: 0', $this->commandTester->getDisplay());
    }

    public function testExecuteWithEndCheckpointSucceeds(): void
    {
        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);
        $this->analysisService
            ->expects($this->once())
            ->method('generateForUser')
            ->with($user, 'end')
            ->willReturn($this->makeAnalysis());

        $exitCode = $this->commandTester->execute(['--checkpoint' => 'end']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithInvalidCheckpointReturnsFail(): void
    {
        $exitCode = $this->commandTester->execute(['--checkpoint' => 'invalid']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid checkpoint', $this->commandTester->getDisplay());
    }

    public function testExecuteSkipsUsersWhenServiceReturnsNull(): void
    {
        $user1 = $this->makeUser(1);
        $user2 = $this->makeUser(2);

        $this->userRepository->method('findAll')->willReturn([$user1, $user2]);
        $this->analysisService->method('generateForUser')->willReturnOnConsecutiveCalls(
            $this->makeAnalysis(),
            null
        );

        $exitCode = $this->commandTester->execute(['--checkpoint' => 'mid']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Generated: 1, Skipped: 1', $this->commandTester->getDisplay());
    }

    public function testExecuteWithNoUsersSucceeds(): void
    {
        $this->userRepository->method('findAll')->willReturn([]);

        $exitCode = $this->commandTester->execute(['--checkpoint' => 'mid']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Generated: 0, Skipped: 0', $this->commandTester->getDisplay());
    }

    public function testDefaultCheckpointIsMid(): void
    {
        $user = $this->makeUser(1);
        $this->userRepository->method('findAll')->willReturn([$user]);
        $this->analysisService
            ->expects($this->once())
            ->method('generateForUser')
            ->with($user, 'mid')
            ->willReturn($this->makeAnalysis());

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $user->setEmail("user{$id}@example.com");
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
        $analysis->setCheckpoint('mid');
        $analysis->setContent('Analysis content');

        $ref = new \ReflectionProperty(FinancialAnalysis::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($analysis, $id);

        return $analysis;
    }
}
