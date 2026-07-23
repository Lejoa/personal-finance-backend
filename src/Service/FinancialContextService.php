<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Repository\TipRepository;
use App\Repository\TransactionRepository;
use App\ValueObject\PeriodHint;

class FinancialContextService
{
    public function __construct(
        private BudgetRepository $budgetRepository,
        private TransactionRepository $transactionRepository,
        private TipRepository $tipRepository,
        private FinancialDigestService $digestService,
        private HistoricalFinancialQueryService $historicalService,
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * Builds the user's financial context for the LLM service.
     */
    public function buildContext(User $user): array
    {
        $startOfMonth = new \DateTime('first day of this month');
        $startOfMonth->setTime(0, 0, 0);
        $endOfMonth = new \DateTime('last day of this month');
        $endOfMonth->setTime(23, 59, 59);

        $transactions = $this->transactionRepository->findByFilters(
            $user,
            null,
            $startOfMonth,
            $endOfMonth
        );

        $totalIncome = $this->calculateTotalByType($transactions, 'ingreso');
        $totalExpenses = $this->calculateTotalByType($transactions, 'gasto');
        $categorySpending = $this->groupExpensesByCategory($transactions);
        $savingsRate = $this->calculateSavingRate($totalIncome, $totalExpenses);

        $categories = array_map(
            static fn (string $name, float $amount) => ['name' => $name, 'amount' => $amount],
            array_keys($categorySpending),
            array_values($categorySpending)
        );

        $budgets = $this->budgetRepository->findBy(['user' => $user]);
        $formattedBudgets = $this->formatBudgets($budgets, $categorySpending, $startOfMonth, $endOfMonth);

        $previousTotals = $this->transactionRepository->getPreviousMonthTotals($user);
        $previousSavingsRate = $this->calculateSavingRate(
            $previousTotals['income'],
            $previousTotals['expenses']
        );

        $topTip = $this->tipRepository->findOneBy([], ['id' => 'DESC']);

        // Compute the user's financial level from the pre-computed digest.
        $digest = $this->digestService->getDigest($user);
        $financialLevel = $this->digestService->computeFinancialLevel($user, $digest);

        // All expense and income category names available in the app.
        // Sent to the LLM so it can map user synonyms (e.g. "alimentación" → "Comida")
        // to real category names when extracting transaction data or period_hint.category.
        $allCategories = array_map(
            static fn ($cat) => $cat->getName(),
            $this->categoryRepository->findAll()
        );

        return [
            'userContext' => [
                'currency' => 'COP',
                'locale' => 'es-CO',
                'financial_level' => $financialLevel,
            ],
            'summary' => [
                'period' => $startOfMonth->format('Y-m'),
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'savings_rate' => $savingsRate,
                'previous_savings_rate' => $previousSavingsRate,
                'previous_income' => $previousTotals['income'],
                'previous_expenses' => $previousTotals['expenses'],
            ],
            'categories' => $categories,
            'budgets' => $formattedBudgets,
            'top_tip' => $topTip ? $topTip->getTitle() . ': ' . $topTip->getShortDescription() : null,
            'available_categories' => $allCategories,
        ];
    }

    /**
     * Builds additional context string based on the LLM-classified context type.
     *
     * For context_type "historical", delegates to HistoricalFinancialQueryService,
     * which handles both exact-period queries (when PeriodHint is valid) and the
     * compact 6-month snapshot fallback (when PeriodHint is null).
     * All other context types use the pre-computed digest as before.
     *
     * Returns an empty string when no extra context is needed (context_type = "none").
     */
    public function buildAdditionalContext(User $user, string $contextType, ?PeriodHint $periodHint = null): string
    {
        if ('none' === $contextType) {
            return '';
        }

        if ('historical' === $contextType) {
            return $this->historicalService->buildContext($user, $periodHint);
        }

        $digest = $this->digestService->getDigest($user);

        return match ($contextType) {
            'trends'     => $this->formatTrendsContext($digest),
            'budget'     => $this->formatBudgetDetailContext($digest),
            'categories' => $this->formatCategoriesRankingContext($digest),
            'savings'    => $this->formatSavingsContext($digest),
            default      => '',
        };
    }

    private function formatTrendsContext(array $digest): string
    {
        $lines = [];

        $prevIncome = $digest['previous_income'] ?? 0.0;
        $prevExpenses = $digest['previous_expenses'] ?? 0.0;
        $prevSavingsRate = $digest['previous_savings_rate'] ?? 0.0;

        $prevIncomeFormatted = number_format($prevIncome, 0, ',', '.');
        $prevExpensesFormatted = number_format($prevExpenses, 0, ',', '.');

        $lines[] = 'Resumen del mes pasado:';
        $lines[] = "- Ingresos: \${$prevIncomeFormatted} COP";
        $lines[] = "- Gastos: \${$prevExpensesFormatted} COP";
        $lines[] = "- Tasa de ahorro: {$prevSavingsRate}%";

        if (!empty($digest['category_trends'])) {
            $lines[] = '';
            $lines[] = 'Comparación por categoría (promedio 3 meses vs. este mes):';
            foreach ($digest['category_trends'] as $trend) {
                $delta = $trend['delta_pct'] >= 0 ? "+{$trend['delta_pct']}%" : "{$trend['delta_pct']}%";
                $avg = number_format($trend['avg_3_months'], 0, ',', '.');
                $current = number_format($trend['current_month'], 0, ',', '.');
                $lines[] = "- {$trend['name']}: promedio \${$avg} COP, este mes \${$current} COP ({$delta})";
            }
        }

        // Append current budget status to cover compound questions such as
        // "am I over budget compared to last month?", which require both the previous
        // month's history and the current month's budget state in the same context.
        if (!empty($digest['budget_health'])) {
            $lines[] = '';
            $lines[] = 'Estado actual de presupuestos (mes en curso):';
            foreach ($digest['budget_health'] as $bh) {
                $limit   = number_format($bh['limit'], 0, ',', '.');
                $spent   = number_format($bh['spent'], 0, ',', '.');
                $lines[] = "- {$bh['name']}: {$bh['pct_used']}% usado (\${$spent} de \${$limit} COP), quedan {$bh['days_remaining']} días";
            }
        }

        return implode("\n", $lines);
    }

    private function formatBudgetDetailContext(array $digest): string
    {
        if (empty($digest['budget_health'])) {
            return '';
        }

        $parts = [];
        foreach ($digest['budget_health'] as $bh) {
            $parts[] = "{$bh['name']}: {$bh['pct_used']}% usado, quedan {$bh['days_remaining']} días";
        }

        return 'Detalle de presupuestos: ' . implode('; ', $parts);
    }

    private function formatCategoriesRankingContext(array $digest): string
    {
        if (empty($digest['category_trends'])) {
            return '';
        }

        $lines = ['Ranking de gastos por categoría este mes:'];
        foreach ($digest['category_trends'] as $i => $trend) {
            $pos = $i + 1;
            $current = number_format($trend['current_month'], 0, ',', '.');
            $avg = number_format($trend['avg_3_months'], 0, ',', '.');
            $lines[] = "- {$pos}. {$trend['name']}: \${$current} COP (promedio 3 meses: \${$avg} COP)";
        }

        return implode("\n", $lines);
    }

    private function formatSavingsContext(array $digest): string
    {
        $velocity = number_format($digest['spending_velocity'], 0, ',', '.');
        $projected = number_format($digest['projected_expenses'], 0, ',', '.');
        $prevRate = $digest['previous_savings_rate'];

        return "Proyección de ahorro: Velocidad de gasto \${$velocity} COP/día. "
             . "Proyección fin de mes: \${$projected} COP en gastos. "
             . "Tasa de ahorro mes anterior: {$prevRate}%.";
    }

    private function calculateTotalByType(array $transactions, string $type): float
    {
        $total = 0.0;
        foreach ($transactions as $transaction) {
            if ($transaction->getType() === $type) {
                $total += $transaction->getAmount();
            }
        }

        return $total;
    }

    private function groupExpensesByCategory(array $transactions): array
    {
        $spending = [];
        foreach ($transactions as $transaction) {
            if ('gasto' !== $transaction->getType()) {
                continue;
            }
            $categoryName = $transaction->getCategory()
                ? $transaction->getCategory()->getName()
                : 'Sin categoría';

            if (!isset($spending[$categoryName])) {
                $spending[$categoryName] = 0.0;
            }
            $spending[$categoryName] += $transaction->getAmount();
        }

        return $spending;
    }

    private function calculateSavingRate(float $totalIncome, float $totalExpenses): float
    {
        if ($totalIncome <= 0) {
            return 0.0;
        }

        return round((($totalIncome - $totalExpenses) / $totalIncome) * 100, 2);
    }

    private function formatBudgets(
        array $budgets,
        array $categorySpending,
        \DateTime $startOfMonth,
        \DateTime $endOfMonth
    ): array {
        $overlapping = array_filter(
            $budgets,
            static fn ($budget) => !($budget->getEndDate() < $startOfMonth || $budget->getStartDate() > $endOfMonth)
        );

        return $this->formatBudgetsForCategorySpending($overlapping, $categorySpending);
    }

    /**
     * Maps already-filtered budgets into the {name, limit, spent} shape sent to the LLM.
     * Shared by formatBudgets() (this-month, PHP-side overlap filter) and
     * buildContextForPeriod() (arbitrary historical month, DB-side overlap filter via
     * BudgetRepository::findOverlapping()) so the mapping logic isn't duplicated.
     *
     * @param iterable<\App\Entity\Budget> $budgets
     */
    private function formatBudgetsForCategorySpending(iterable $budgets, array $categorySpending): array
    {
        $formatted = [];
        foreach ($budgets as $budget) {
            foreach ($budget->getBudgetCategories() as $budgetCategory) {
                $categoryName = $budgetCategory->getCategory()->getName();
                $limit = $budgetCategory->getAmount();
                $spent = $categorySpending[$categoryName] ?? 0.0;
                $formatted[] = ['name' => $categoryName, 'limit' => $limit, 'spent' => $spent];
            }
        }

        return $formatted;
    }

    /**
     * Builds the financial context for an arbitrary already-closed historical month
     * (format YYYY-MM), for use by the analysis backfill command.
     *
     * financial_level is intentionally computed from the user's CURRENT digest/level
     * (not a historical one) — it represents a slowly-changing trait (pedagogical tone
     * calibration), not a per-month fact, and FinancialDigestService is not period-aware
     * by design. Trend/velocity/consistency signals are simply omitted here since they
     * are inherently "current month" concepts that don't apply to a closed past month.
     */
    public function buildContextForPeriod(User $user, string $period): array
    {
        $periodStart = new \DateTime($period . '-01');
        $periodStart->setTime(0, 0, 0);
        $periodEnd = (clone $periodStart)->modify('last day of this month');
        $periodEnd->setTime(23, 59, 59);

        $totals = $this->getMonthTotals($user, $period);
        $categorySpending = $this->getMonthCategorySpending($user, $period);
        $savingsRate = $this->calculateSavingRate($totals['income'], $totals['expenses']);

        $categories = array_map(
            static fn (string $name, float $amount) => ['name' => $name, 'amount' => $amount],
            array_keys($categorySpending),
            array_values($categorySpending)
        );

        $budgets = $this->budgetRepository->findOverlapping($user, $periodStart, $periodEnd);
        $formattedBudgets = $this->formatBudgetsForCategorySpending($budgets, $categorySpending);

        $previousPeriod = (clone $periodStart)->modify('-1 month')->format('Y-m');
        $previousTotals = $this->getMonthTotals($user, $previousPeriod);
        $previousSavingsRate = $this->calculateSavingRate($previousTotals['income'], $previousTotals['expenses']);

        $topTip = $this->tipRepository->findOneBy([], ['id' => 'DESC']);

        $digest = $this->digestService->getDigest($user);
        $financialLevel = $this->digestService->computeFinancialLevel($user, $digest);

        $allCategories = array_map(
            static fn ($cat) => $cat->getName(),
            $this->categoryRepository->findAll()
        );

        return [
            'userContext' => [
                'currency' => 'COP',
                'locale' => 'es-CO',
                'financial_level' => $financialLevel,
            ],
            'summary' => [
                'period' => $period,
                'total_income' => $totals['income'],
                'total_expenses' => $totals['expenses'],
                'savings_rate' => $savingsRate,
                'previous_savings_rate' => $previousSavingsRate,
                'previous_income' => $previousTotals['income'],
                'previous_expenses' => $previousTotals['expenses'],
            ],
            'categories' => $categories,
            'budgets' => $formattedBudgets,
            'top_tip' => $topTip ? $topTip->getTitle() . ': ' . $topTip->getShortDescription() : null,
            'available_categories' => $allCategories,
        ];
    }

    /**
     * @return array{income: float, expenses: float}
     */
    private function getMonthTotals(User $user, string $period): array
    {
        $rows = $this->transactionRepository->getMonthlyTotalsForRange($user, $period, $period);
        $row = $rows[0] ?? null;

        return [
            'income' => $row['income'] ?? 0.0,
            'expenses' => $row['expenses'] ?? 0.0,
        ];
    }

    /**
     * @return array<string, float> category name => total spent
     */
    private function getMonthCategorySpending(User $user, string $period): array
    {
        $rows = $this->transactionRepository->getMonthlyCategorySpendingForRange($user, $period, $period);
        $spending = [];
        foreach ($rows as $row) {
            $spending[$row['category']] = $row['total'];
        }

        return $spending;
    }
}
