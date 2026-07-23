<?php

namespace App\Tests\Unit\Service;

use App\Entity\FinancialAnalysis;
use App\Entity\User;
use App\Repository\FinancialAnalysisRepository;
use App\Service\AnalysisService;
use App\Service\FinancialContextService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AnalysisServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private FinancialAnalysisRepository&MockObject $analysisRepo;
    private FinancialContextService&MockObject $contextService;
    private NotificationService&MockObject $notificationService;
    private HttpClientInterface&MockObject $httpClient;
    private AnalysisService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->analysisRepo = $this->createMock(FinancialAnalysisRepository::class);
        $this->contextService = $this->createMock(FinancialContextService::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->service = new AnalysisService(
            $this->em,
            $this->analysisRepo,
            $this->contextService,
            $this->notificationService,
            $this->httpClient,
            'http://localhost:9999'
        );
    }

    // ── generateForUser ─────────────────────────────────────────────────────

    public function testGenerateForUserReturnsNullWhenAnalysisAlreadyExists(): void
    {
        $user = $this->makeUser();
        $existing = $this->makeAnalysis();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn($existing);

        $this->httpClient->expects($this->never())->method('request');

        $result = $this->service->generateForUser($user, 'mid');

        $this->assertNull($result);
    }

    public function testGenerateForUserPersistsAndReturnsAnalysis(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn(null);

        $this->contextService->method('buildContext')->willReturn([
            'userContext' => ['currency' => 'COP'],
            'summary' => ['total_income' => 1_000_000.0],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
        ]);

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'insights' => [['message' => 'Tu análisis financiero está listo.']],
        ]);
        $this->httpClient->method('request')->willReturn($httpResponse);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->notificationService->expects($this->once())->method('createForAnalysis');

        $result = $this->service->generateForUser($user, 'mid');

        $this->assertInstanceOf(FinancialAnalysis::class, $result);
        $this->assertSame('Tu análisis financiero está listo.', $result->getContent());
        $this->assertSame('mid', $result->getCheckpoint());
    }

    public function testGenerateForUserReturnsNullWhenLlmReturnsNoContent(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn(null);

        $this->contextService->method('buildContext')->willReturn([
            'userContext' => [],
            'summary' => [],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
        ]);

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn(['insights' => []]);
        $this->httpClient->method('request')->willReturn($httpResponse);

        $this->em->expects($this->never())->method('persist');

        $result = $this->service->generateForUser($user, 'end');

        $this->assertNull($result);
    }

    public function testGenerateForUserRethrowsHttpException(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn(null);

        $this->contextService->method('buildContext')->willReturn([
            'userContext' => [],
            'summary' => [],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
        ]);

        $this->httpClient->method('request')->willThrowException(new \RuntimeException('LLM service down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LLM service down');

        $this->service->generateForUser($user, 'mid');
    }

    // ── generateForPeriod ───────────────────────────────────────────────────

    public function testGenerateForPeriodReturnsNullWhenAnalysisAlreadyExists(): void
    {
        $user = $this->makeUser();
        $existing = $this->makeAnalysis('end');
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn($existing);

        $this->contextService->expects($this->never())->method('buildContextForPeriod');
        $this->httpClient->expects($this->never())->method('request');

        $result = $this->service->generateForPeriod($user, '2025-03');

        $this->assertNull($result);
    }

    public function testGenerateForPeriodPersistsAndReturnsAnalysis(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn(null);

        $this->contextService->method('buildContextForPeriod')->willReturn([
            'userContext' => ['currency' => 'COP'],
            'summary' => ['period' => '2025-03', 'total_income' => 1_000_000.0],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
        ]);

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'insights' => [['message' => 'Tu análisis de marzo está listo.']],
        ]);
        $this->httpClient->method('request')->willReturn($httpResponse);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->notificationService->expects($this->never())->method('createForAnalysis');

        $result = $this->service->generateForPeriod($user, '2025-03');

        $this->assertInstanceOf(FinancialAnalysis::class, $result);
        $this->assertSame('Tu análisis de marzo está listo.', $result->getContent());
        $this->assertSame('2025-03', $result->getPeriod());
        $this->assertSame('end', $result->getCheckpoint());
    }

    public function testGenerateForPeriodSetsCheckpointEndByDefault(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')
            ->with($user, '2025-03', 'end')
            ->willReturn(null);

        $this->contextService->method('buildContextForPeriod')->willReturn([
            'userContext' => [],
            'summary' => [],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
        ]);

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn(['insights' => [['message' => 'Contenido']]]);
        $this->httpClient->method('request')->willReturn($httpResponse);

        $result = $this->service->generateForPeriod($user, '2025-03');

        $this->assertSame('end', $result->getCheckpoint());
    }

    public function testGenerateForPeriodReturnsNullWhenLlmReturnsNoContent(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn(null);

        $this->contextService->method('buildContextForPeriod')->willReturn([
            'userContext' => [],
            'summary' => [],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
        ]);

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn(['insights' => []]);
        $this->httpClient->method('request')->willReturn($httpResponse);

        $this->em->expects($this->never())->method('persist');
        $this->notificationService->expects($this->never())->method('createForAnalysis');

        $result = $this->service->generateForPeriod($user, '2025-03');

        $this->assertNull($result);
    }

    public function testGenerateForPeriodRethrowsHttpException(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findOneByUserPeriodCheckpoint')->willReturn(null);

        $this->contextService->method('buildContextForPeriod')->willReturn([
            'userContext' => [],
            'summary' => [],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
        ]);

        $this->httpClient->method('request')->willThrowException(new \RuntimeException('LLM service down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LLM service down');

        $this->service->generateForPeriod($user, '2025-03');
    }

    // ── getUserAnalyses ─────────────────────────────────────────────────────

    public function testGetUserAnalysesDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $analysis = $this->makeAnalysis();
        $this->analysisRepo->method('findByUser')->with($user)->willReturn([$analysis]);

        $result = $this->service->getUserAnalyses($user);

        $this->assertCount(1, $result);
        $this->assertSame($analysis, $result[0]);
    }

    // ── findByIdAndUser ─────────────────────────────────────────────────────

    public function testFindByIdAndUserDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $analysis = $this->makeAnalysis();
        $this->analysisRepo->method('findByIdForUser')->with(1, $user)->willReturn($analysis);

        $result = $this->service->findByIdAndUser(1, $user);

        $this->assertSame($analysis, $result);
    }

    public function testFindByIdAndUserReturnsNullWhenNotFound(): void
    {
        $user = $this->makeUser();
        $this->analysisRepo->method('findByIdForUser')->willReturn(null);

        $this->assertNull($this->service->findByIdAndUser(99, $user));
    }

    // ── markAsRead ──────────────────────────────────────────────────────────

    public function testMarkAsReadSetsIsReadAndFlushes(): void
    {
        $analysis = $this->makeAnalysis();
        $analysis->setIsRead(false);

        $this->em->expects($this->once())->method('flush');

        $this->service->markAsRead($analysis);

        $this->assertTrue($analysis->isRead());
    }

    public function testMarkAsReadSkipsFlushWhenAlreadyRead(): void
    {
        $analysis = $this->makeAnalysis();
        $analysis->setIsRead(true);

        $this->em->expects($this->never())->method('flush');

        $this->service->markAsRead($analysis);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function makeUser(int $id = 1): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    private function makeAnalysis(string $checkpoint = 'mid'): FinancialAnalysis
    {
        $analysis = new FinancialAnalysis();
        $analysis->setUser($this->makeUser());
        $analysis->setCheckpoint($checkpoint);
        $analysis->setPeriod((new \DateTime())->format('Y-m'));
        $analysis->setContent('Analysis content');
        $ref = new \ReflectionProperty(FinancialAnalysis::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($analysis, 1);

        return $analysis;
    }
}
