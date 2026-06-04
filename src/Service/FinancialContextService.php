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
        $formatted = [];
        foreach ($budgets as $budget) {
            if ($budget->getEndDate() < $startOfMonth || $budget->getStartDate() > $endOfMonth) {
                continue;
            }
            foreach ($budget->getBudgetCategories() as $budgetCategory) {
                $categoryName = $budgetCategory->getCategory()->getName();
                $limit = $budgetCategory->getAmount();
                $spent = $categorySpending[$categoryName] ?? 0.0;
                $formatted[] = ['name' => $categoryName, 'limit' => $limit, 'spent' => $spent];
            }
        }

        return $formatted;
    }
}
