<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Repository\TipRepository;
use App\Repository\TransactionRepository;
use App\Service\FinancialContextService;
use App\Service\FinancialDigestService;
use App\Service\HistoricalFinancialQueryService;
use App\ValueObject\PeriodHint;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FinancialContextServiceTest extends TestCase
{
    private BudgetRepository&MockObject $budgetRepo;
    private TransactionRepository&MockObject $transactionRepo;
    private TipRepository&MockObject $tipRepo;
    private FinancialDigestService&MockObject $digestService;
    private HistoricalFinancialQueryService&MockObject $historicalService;
    private CategoryRepository&MockObject $categoryRepo;
    private FinancialContextService $service;

    protected function setUp(): void
    {
        $this->budgetRepo        = $this->createMock(BudgetRepository::class);
        $this->transactionRepo   = $this->createMock(TransactionRepository::class);
        $this->tipRepo           = $this->createMock(TipRepository::class);
        $this->digestService     = $this->createMock(FinancialDigestService::class);
        $this->historicalService = $this->createMock(HistoricalFinancialQueryService::class);
        $this->categoryRepo      = $this->createMock(CategoryRepository::class);

        $this->service = new FinancialContextService(
            $this->budgetRepo,
            $this->transactionRepo,
            $this->tipRepo,
            $this->digestService,
            $this->historicalService,
            $this->categoryRepo,
        );
    }

    // ── buildContext ────────────────────────────────────────────────────────

    public function testBuildContextReturnsExpectedStructure(): void
    {
        $this->stubBuildContext();

        $result = $this->service->buildContext($this->makeUser());

        $this->assertArrayHasKey('userContext', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('budgets', $result);
        $this->assertArrayHasKey('top_tip', $result);
        $this->assertArrayHasKey('available_categories', $result);
    }

    public function testBuildContextUserContextContainsCurrency(): void
    {
        $this->stubBuildContext(financialLevel: 'avanzado');

        $result = $this->service->buildContext($this->makeUser());

        $this->assertSame('COP', $result['userContext']['currency']);
        $this->assertSame('es-CO', $result['userContext']['locale']);
        $this->assertSame('avanzado', $result['userContext']['financial_level']);
    }

    public function testBuildContextSummaryContainsTotals(): void
    {
        $this->stubBuildContext(previousIncome: 500000.0, previousExpenses: 200000.0);

        $result = $this->service->buildContext($this->makeUser());

        $this->assertSame(0.0, $result['summary']['total_income']);
        $this->assertSame(0.0, $result['summary']['total_expenses']);
        $this->assertArrayHasKey('savings_rate', $result['summary']);
        $this->assertArrayHasKey('previous_income', $result['summary']);
        $this->assertArrayHasKey('previous_expenses', $result['summary']);
    }

    public function testBuildContextTopTipIsNullWhenNoTips(): void
    {
        $this->stubBuildContext();

        $result = $this->service->buildContext($this->makeUser());

        $this->assertNull($result['top_tip']);
    }

    public function testBuildContextAvailableCategoriesIsAnArray(): void
    {
        $this->stubBuildContext();

        $result = $this->service->buildContext($this->makeUser());

        $this->assertIsArray($result['available_categories']);
    }

    // ── buildAdditionalContext ──────────────────────────────────────────────

    public function testBuildAdditionalContextReturnsEmptyStringForNone(): void
    {
        $this->digestService->expects($this->never())->method('getDigest');

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'none');

        $this->assertSame('', $result);
    }

    public function testBuildAdditionalContextReturnsTrendsContext(): void
    {
        $this->digestService->method('getDigest')->willReturn([
            'previous_income'       => 4000000.0,
            'previous_expenses'     => 2500000.0,
            'previous_savings_rate' => 37.5,
            'category_trends'       => [
                ['name' => 'Comida', 'current_month' => 300000.0, 'avg_3_months' => 250000.0, 'delta_pct' => 20.0],
            ],
            'budget_health' => [],
        ]);

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'trends');

        $this->assertStringContainsString('Comida', $result);
        $this->assertStringContainsString('promedio', $result);
        $this->assertStringContainsString('4.000.000', $result);
    }

    public function testBuildAdditionalContextTrendsIncludesBudgetStatusWhenPresent(): void
    {
        $this->digestService->method('getDigest')->willReturn([
            'previous_income'       => 4000000.0,
            'previous_expenses'     => 2500000.0,
            'previous_savings_rate' => 37.5,
            'category_trends'       => [],
            'budget_health'         => [
                ['name' => 'Comida', 'pct_used' => 80.0, 'limit' => 500000.0, 'spent' => 400000.0, 'days_remaining' => 5],
            ],
        ]);

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'trends');

        $this->assertStringContainsString('Comida', $result);
        $this->assertStringContainsString('80%', $result);
    }

    public function testBuildAdditionalContextReturnsBudgetContext(): void
    {
        $this->digestService->method('getDigest')->willReturn([
            'budget_health' => [
                ['name' => 'Comida', 'pct_used' => 75.0, 'days_remaining' => 10],
            ],
        ]);

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'budget');

        $this->assertStringContainsString('Comida', $result);
        $this->assertStringContainsString('75%', $result);
    }

    public function testBuildAdditionalContextReturnsCategoriesContext(): void
    {
        $this->digestService->method('getDigest')->willReturn([
            'category_trends' => [
                ['name' => 'Transporte', 'current_month' => 150000.0, 'avg_3_months' => 120000.0, 'delta_pct' => 25.0],
            ],
        ]);

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'categories');

        $this->assertStringContainsString('Transporte', $result);
        $this->assertStringContainsString('Ranking', $result);
    }

    public function testBuildAdditionalContextReturnsSavingsContext(): void
    {
        $this->digestService->method('getDigest')->willReturn([
            'spending_velocity'     => 50000.0,
            'projected_expenses'    => 1_500_000.0,
            'previous_savings_rate' => 20.0,
        ]);

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'savings');

        $this->assertStringContainsString('Proyección', $result);
        $this->assertStringContainsString('20%', $result);
    }

    public function testBuildAdditionalContextDelegatesHistoricalToHistoricalService(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-03', 'to_month' => '2026-05']);

        $this->historicalService
            ->expects($this->once())
            ->method('buildContext')
            ->with($this->isInstanceOf(User::class), $hint)
            ->willReturn('historical context text');

        $this->digestService->expects($this->never())->method('getDigest');

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'historical', $hint);

        $this->assertSame('historical context text', $result);
    }

    public function testBuildAdditionalContextHistoricalWithNullHintDelegatesWithNull(): void
    {
        $this->historicalService
            ->expects($this->once())
            ->method('buildContext')
            ->with($this->isInstanceOf(User::class), null)
            ->willReturn('compact snapshot');

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'historical', null);

        $this->assertSame('compact snapshot', $result);
    }

    public function testBuildAdditionalContextReturnsEmptyStringForUnknownType(): void
    {
        $this->digestService->method('getDigest')->willReturn([]);

        $result = $this->service->buildAdditionalContext($this->makeUser(), 'unknown_type');

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

    /**
     * Stubs the dependencies used by buildContext() with sensible defaults
     * so individual tests only need to override what they are testing.
     */
    private function stubBuildContext(
        float $previousIncome = 0.0,
        float $previousExpenses = 0.0,
        string $financialLevel = 'principiante',
    ): void {
        $this->transactionRepo->method('findByFilters')->willReturn([]);
        $this->transactionRepo->method('getPreviousMonthTotals')->willReturn([
            'income'   => $previousIncome,
            'expenses' => $previousExpenses,
        ]);
        $this->budgetRepo->method('findBy')->willReturn([]);
        $this->tipRepo->method('findOneBy')->willReturn(null);
        $this->digestService->method('getDigest')->willReturn([]);
        $this->digestService->method('computeFinancialLevel')->willReturn($financialLevel);
        $this->categoryRepo->method('findAll')->willReturn([]);
    }
}
