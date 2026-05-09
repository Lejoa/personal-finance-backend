<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\TipRepository;
use App\Repository\TransactionRepository;
use App\Service\FinancialContextService;
use App\Service\FinancialDigestService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FinancialContextServiceTest extends TestCase
{
    private BudgetRepository&MockObject $budgetRepo;
    private TransactionRepository&MockObject $transactionRepo;
    private TipRepository&MockObject $tipRepo;
    private FinancialDigestService&MockObject $digestService;
    private FinancialContextService $service;

    protected function setUp(): void
    {
        $this->budgetRepo = $this->createMock(BudgetRepository::class);
        $this->transactionRepo = $this->createMock(TransactionRepository::class);
        $this->tipRepo = $this->createMock(TipRepository::class);
        $this->digestService = $this->createMock(FinancialDigestService::class);

        $this->service = new FinancialContextService(
            $this->budgetRepo,
            $this->transactionRepo,
            $this->tipRepo,
            $this->digestService
        );
    }

    // ── buildContext ────────────────────────────────────────────────────────

    public function testBuildContextReturnsExpectedStructure(): void
    {
        $user = $this->makeUser();

        $this->transactionRepo->method('findByFilters')->willReturn([]);
        $this->transactionRepo->method('getPreviousMonthTotals')->willReturn([
            'income' => 0.0,
            'expenses' => 0.0,
        ]);
        $this->budgetRepo->method('findBy')->willReturn([]);
        $this->tipRepo->method('findOneBy')->willReturn(null);
        $this->digestService->method('getDigest')->willReturn([]);
        $this->digestService->method('computeFinancialLevel')->willReturn('principiante');

        $result = $this->service->buildContext($user);

        $this->assertArrayHasKey('userContext', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('budgets', $result);
        $this->assertArrayHasKey('top_tip', $result);
    }

    public function testBuildContextUserContextContainsCurrency(): void
    {
        $user = $this->makeUser();

        $this->transactionRepo->method('findByFilters')->willReturn([]);
        $this->transactionRepo->method('getPreviousMonthTotals')->willReturn(['income' => 0.0, 'expenses' => 0.0]);
        $this->budgetRepo->method('findBy')->willReturn([]);
        $this->tipRepo->method('findOneBy')->willReturn(null);
        $this->digestService->method('getDigest')->willReturn([]);
        $this->digestService->method('computeFinancialLevel')->willReturn('avanzado');

        $result = $this->service->buildContext($user);

        $this->assertSame('COP', $result['userContext']['currency']);
        $this->assertSame('es-CO', $result['userContext']['locale']);
        $this->assertSame('avanzado', $result['userContext']['financial_level']);
    }

    public function testBuildContextSummaryContainsTotals(): void
    {
        $user = $this->makeUser();

        $this->transactionRepo->method('findByFilters')->willReturn([]);
        $this->transactionRepo->method('getPreviousMonthTotals')->willReturn(['income' => 500000.0, 'expenses' => 200000.0]);
        $this->budgetRepo->method('findBy')->willReturn([]);
        $this->tipRepo->method('findOneBy')->willReturn(null);
        $this->digestService->method('getDigest')->willReturn([]);
        $this->digestService->method('computeFinancialLevel')->willReturn('principiante');

        $result = $this->service->buildContext($user);

        $this->assertSame(0.0, $result['summary']['total_income']);
        $this->assertSame(0.0, $result['summary']['total_expenses']);
        $this->assertArrayHasKey('savings_rate', $result['summary']);
    }

    public function testBuildContextTopTipIsNullWhenNoTips(): void
    {
        $user = $this->makeUser();

        $this->transactionRepo->method('findByFilters')->willReturn([]);
        $this->transactionRepo->method('getPreviousMonthTotals')->willReturn(['income' => 0.0, 'expenses' => 0.0]);
        $this->budgetRepo->method('findBy')->willReturn([]);
        $this->tipRepo->method('findOneBy')->willReturn(null);
        $this->digestService->method('getDigest')->willReturn([]);
        $this->digestService->method('computeFinancialLevel')->willReturn('principiante');

        $result = $this->service->buildContext($user);

        $this->assertNull($result['top_tip']);
    }

    // ── buildAdditionalContext ──────────────────────────────────────────────

    public function testBuildAdditionalContextReturnsEmptyStringForNone(): void
    {
        $user = $this->makeUser();

        $result = $this->service->buildAdditionalContext($user, 'none');

        $this->assertSame('', $result);
        $this->digestService->expects($this->never())->method('getDigest');
    }

    public function testBuildAdditionalContextReturnsTrendsContext(): void
    {
        $user = $this->makeUser();

        $this->digestService->method('getDigest')->willReturn([
            'category_trends' => [
                ['name' => 'Comida', 'current_month' => 300000.0, 'avg_3_months' => 250000.0, 'delta_pct' => 20.0],
            ],
        ]);

        $result = $this->service->buildAdditionalContext($user, 'trends');

        $this->assertStringContainsString('Comida', $result);
        $this->assertStringContainsString('promedio', $result);
    }

    public function testBuildAdditionalContextReturnsBudgetContext(): void
    {
        $user = $this->makeUser();

        $this->digestService->method('getDigest')->willReturn([
            'budget_health' => [
                ['name' => 'Comida', 'pct_used' => 75.0, 'days_remaining' => 10],
            ],
        ]);

        $result = $this->service->buildAdditionalContext($user, 'budget');

        $this->assertStringContainsString('Comida', $result);
        $this->assertStringContainsString('75%', $result);
    }

    public function testBuildAdditionalContextReturnsCategoriesContext(): void
    {
        $user = $this->makeUser();

        $this->digestService->method('getDigest')->willReturn([
            'category_trends' => [
                ['name' => 'Transporte', 'current_month' => 150000.0, 'avg_3_months' => 120000.0, 'delta_pct' => 25.0],
            ],
        ]);

        $result = $this->service->buildAdditionalContext($user, 'categories');

        $this->assertStringContainsString('Transporte', $result);
        $this->assertStringContainsString('Ranking', $result);
    }

    public function testBuildAdditionalContextReturnsSavingsContext(): void
    {
        $user = $this->makeUser();

        $this->digestService->method('getDigest')->willReturn([
            'spending_velocity' => 50000.0,
            'projected_expenses' => 1_500_000.0,
            'previous_savings_rate' => 20.0,
        ]);

        $result = $this->service->buildAdditionalContext($user, 'savings');

        $this->assertStringContainsString('Proyección', $result);
        $this->assertStringContainsString('20%', $result);
    }

    public function testBuildAdditionalContextReturnsEmptyStringForUnknownType(): void
    {
        $user = $this->makeUser();

        $this->digestService->method('getDigest')->willReturn([]);

        $result = $this->service->buildAdditionalContext($user, 'unknown_type');

        $this->assertSame('', $result);
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
}
