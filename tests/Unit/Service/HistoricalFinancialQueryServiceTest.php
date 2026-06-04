<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\HistoricalFinancialQueryService;
use App\ValueObject\PeriodHint;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HistoricalFinancialQueryServiceTest extends TestCase
{
    private TransactionRepository&MockObject $repo;
    private HistoricalFinancialQueryService $service;
    private User $user;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(TransactionRepository::class);
        $this->service = new HistoricalFinancialQueryService($this->repo);

        $this->user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($this->user, 1);
    }

    // ── buildContext — null hint (compact snapshot) ──────────────────────────

    public function testBuildContextReturnsCompactSnapshotWhenHintIsNull(): void
    {
        $this->repo->method('getMonthlyTotalsForRange')->willReturn([
            ['month' => '2026-04', 'income' => 4000000.0, 'expenses' => 2000000.0, 'savings_rate' => 50.0],
        ]);
        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([]);

        $result = $this->service->buildContext($this->user, null);

        $this->assertStringContainsString('Resumen últimos', $result);
        $this->assertStringContainsString('abril 2026', $result);
    }

    public function testBuildContextReturnsEmptyStringWhenNoTransactionsAndHintIsNull(): void
    {
        $this->repo->method('getMonthlyTotalsForRange')->willReturn([]);

        $result = $this->service->buildContext($this->user, null);

        $this->assertSame('', $result);
    }

    // ── buildContext — hint with category ────────────────────────────────────

    public function testBuildContextReturnsCategoryDataWhenHintHasCategory(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-04', 'to_month' => '2026-04', 'category' => 'Comida']);

        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([
            ['month' => '2026-04', 'category' => 'Comida', 'total' => 850000.0],
        ]);

        $result = $this->service->buildContext($this->user, $hint);

        $this->assertStringContainsString('Comida', $result);
        $this->assertStringContainsString('850', $result);
        $this->assertStringContainsString('Total en el período', $result);
    }

    public function testBuildContextReturnsNoRecordsMessageWhenCategoryHasNoData(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-04', 'to_month' => '2026-04', 'category' => 'Viajes']);

        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([]);

        $result = $this->service->buildContext($this->user, $hint);

        $this->assertStringContainsString('Sin registros de Viajes', $result);
    }

    // ── buildContext — hint without category (totals + breakdown) ───────────

    public function testBuildContextReturnsTotalsAndCategoryBreakdownWhenHintHasNoCategory(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-04', 'to_month' => '2026-04']);

        $this->repo->method('getMonthlyTotalsForRange')->willReturn([
            ['month' => '2026-04', 'income' => 4200000.0, 'expenses' => 2900000.0, 'savings_rate' => 31.0],
        ]);
        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([
            ['month' => '2026-04', 'category' => 'Comida', 'total' => 850000.0],
            ['month' => '2026-04', 'category' => 'Transporte', 'total' => 320000.0],
        ]);

        $result = $this->service->buildContext($this->user, $hint);

        $this->assertStringContainsString('Datos históricos', $result);
        $this->assertStringContainsString('ingresos', $result);
        $this->assertStringContainsString('gastos', $result);
        $this->assertStringContainsString('Desglose de gastos por categoría', $result);
        $this->assertStringContainsString('Comida', $result);
        $this->assertStringContainsString('Transporte', $result);
    }

    public function testBuildContextReturnsTotalsPeriodSummaryLine(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-03', 'to_month' => '2026-04']);

        $this->repo->method('getMonthlyTotalsForRange')->willReturn([
            ['month' => '2026-03', 'income' => 4000000.0, 'expenses' => 2000000.0, 'savings_rate' => 50.0],
            ['month' => '2026-04', 'income' => 4200000.0, 'expenses' => 2200000.0, 'savings_rate' => 47.6],
        ]);
        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([]);

        $result = $this->service->buildContext($this->user, $hint);

        $this->assertStringContainsString('Total período', $result);
    }

    public function testBuildContextReturnsNoRecordsMessageWhenNoTotalsData(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-04', 'to_month' => '2026-04']);

        $this->repo->method('getMonthlyTotalsForRange')->willReturn([]);
        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([]);

        $result = $this->service->buildContext($this->user, $hint);

        $this->assertStringContainsString('Sin registros en el período', $result);
    }

    // ── buildContext — truncatedFrom prefix ──────────────────────────────────

    public function testBuildContextPrependsAvailabilityNoteWhenHintWasClamped(): void
    {
        // Build a hint that triggers clamping by using a very old from_month
        $hint = PeriodHint::fromArray([
            'from_month' => '2020-01',
            'to_month'   => (new \DateTime('first day of last month'))->format('Y-m'),
        ]);

        $this->assertNotNull($hint);
        $this->assertNotNull($hint->truncatedFrom, 'Fixture requires a clamped PeriodHint');

        $this->repo->method('getMonthlyTotalsForRange')->willReturn([
            ['month' => $hint->fromMonth, 'income' => 3000000.0, 'expenses' => 2000000.0, 'savings_rate' => 33.3],
        ]);
        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([]);

        $result = $this->service->buildContext($this->user, $hint);

        $this->assertStringContainsString('Nota:', $result, 'Result must start with an availability note when truncatedFrom is set');
    }

    public function testBuildContextDoesNotPrependsNoteWhenHintWasNotClamped(): void
    {
        $hint = PeriodHint::fromArray([
            'from_month' => (new \DateTime('first day of -3 months'))->format('Y-m'),
            'to_month'   => (new \DateTime('first day of last month'))->format('Y-m'),
        ]);

        $this->assertNull($hint->truncatedFrom, 'Fixture requires an unclamped PeriodHint');

        $this->repo->method('getMonthlyTotalsForRange')->willReturn([
            ['month' => $hint->fromMonth, 'income' => 3000000.0, 'expenses' => 2000000.0, 'savings_rate' => 33.3],
        ]);
        $this->repo->method('getMonthlyCategorySpendingForRange')->willReturn([]);

        $result = $this->service->buildContext($this->user, $hint);

        $this->assertStringNotContainsString('Nota:', $result);
    }
}
