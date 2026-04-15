<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\TipRepository;
use App\Repository\TransactionRepository;

class FinancialContextService
{
    public function __construct(
        private BudgetRepository $budgetRepository,
        private TransactionRepository $transactionRepository,
        private TipRepository $tipRepository,
        private FinancialDigestService $digestService
    ) {}

    /**
     * Builds the user's financial context for the LLM service.
     */
    public function buildContext(User $user): array
    {
        $startOfMonth = new \DateTime('first day of this month');
        $startOfMonth->setTime(0,0,0);
        $endOfMonth = new \DateTime('last day of this month');
        $endOfMonth->setTime(23,59,59);

        $transactions = $this->transactionRepository->findByFilters(
            $user, null, $startOfMonth, $endOfMonth
        );

        $totalIncome    = $this->calculateTotalByType($transactions, 'ingreso');
        $totalExpenses  = $this->calculateTotalByType($transactions, 'gasto');
        $categorySpending = $this->groupExpensesByCategory($transactions);
        $savingsRate    = $this->calculateSavingRate($totalIncome, $totalExpenses);

        $categories = array_map(
            fn(string $name, float $amount) => ['name' => $name, 'amount' => $amount],
            array_keys($categorySpending),
            array_values($categorySpending)
        );

        $budgets = $this->budgetRepository->findBy(['user' => $user]);
        $formattedBudgets = $this->formatBudgets($budgets, $categorySpending, $startOfMonth, $endOfMonth);

        $previousTotals      = $this->transactionRepository->getPreviousMonthTotals($user);
        $previousSavingsRate = $this->calculateSavingRate(
            $previousTotals['income'],
            $previousTotals['expenses']
        );

        $topTip = $this->tipRepository->findOneBy([], ['id' => 'DESC']);

        // Compute financial level from digest (Estrategia 3)
        $digest         = $this->digestService->getDigest($user);
        $financialLevel = $this->digestService->computeFinancialLevel($user, $digest);

        return [
            'userContext' => [
                'currency'        => 'COP',
                'locale'          => 'es-CO',
                'financial_level' => $financialLevel,
            ],
            'summary' => [
                'period'                => $startOfMonth->format('Y-m'),
                'total_income'          => $totalIncome,
                'total_expenses'        => $totalExpenses,
                'savings_rate'          => $savingsRate,
                'previous_savings_rate' => $previousSavingsRate,
            ],
            'categories' => $categories,
            'budgets'    => $formattedBudgets,
            'top_tip'    => $topTip ? $topTip->getTitle() . ': ' . $topTip->getShortDescription() : null,
        ];
    }

    /**
     * Builds additional context string based on the LLM-classified context type.
     * Returns an empty string when no extra context is needed (context_type = "none").
     */
    public function buildAdditionalContext(User $user, string $contextType): string
    {
        if ($contextType === 'none') {
            return '';
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
        if (empty($digest['category_trends'])) {
            return '';
        }

        $lines = ['Contexto histórico (últimos 3 meses):'];
        foreach ($digest['category_trends'] as $trend) {
            $delta   = $trend['delta_pct'] >= 0 ? "+{$trend['delta_pct']}%" : "{$trend['delta_pct']}%";
            $avg     = number_format($trend['avg_3_months'], 0, ',', '.');
            $current = number_format($trend['current_month'], 0, ',', '.');
            $lines[] = "- {$trend['name']}: promedio \${$avg} COP, este mes \${$current} COP ({$delta})";
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
            $pos     = $i + 1;
            $current = number_format($trend['current_month'], 0, ',', '.');
            $avg     = number_format($trend['avg_3_months'], 0, ',', '.');
            $lines[] = "- {$pos}. {$trend['name']}: \${$current} COP (promedio 3 meses: \${$avg} COP)";
        }

        return implode("\n", $lines);
    }

    private function formatSavingsContext(array $digest): string
    {
        $velocity  = number_format($digest['spending_velocity'], 0, ',', '.');
        $projected = number_format($digest['projected_expenses'], 0, ',', '.');
        $prevRate  = $digest['previous_savings_rate'];

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
            if ($transaction->getType() !== 'gasto') {
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
        if ($totalIncome <= 0) return 0.0;
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
                $limit  = $budgetCategory->getAmount();
                $spent  = $categorySpending[$categoryName] ?? 0.0;
                $formatted[] = ['name' => $categoryName, 'limit' => $limit, 'spent' => $spent];
            }
        }
        return $formatted;
    }
}
