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
        private TipRepository $tipRepository
    ) {}

    /**
     * Builds the user's financial context for the LLM service
     */
    public function buildContext(User $user): array
    {
        $startOfMonth = new \DateTime('first day of this month');
        $startOfMonth->setTime(0,0,0);
        $endOfMonth = new \DateTime('last day of this month');
        $endOfMonth->setTime(23,59,59);

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
        
        $categories = array_map (
            fn(string $name, float $amount) 
                => ['name' => $name, 'amount' => $amount],
            array_keys($categorySpending),
            array_values($categorySpending)
        );

        $budgets = $this->budgetRepository->findBy(['user' => $user]);
        $formattedBudgets = $this->formatBudgets(
            $budgets,
            $categorySpending, 
            $startOfMonth, 
            $endOfMonth
        );

        $previousTotals       = $this->transactionRepository->getPreviousMonthTotals($user);
        $previousSavingsRate  = $this->calculateSavingRate(
            $previousTotals['income'],
            $previousTotals['expenses']
        );

        $topTip = $this->tipRepository->findOneBy([], ['id' => 'DESC']);

        return [
            'userContext' => [
                'currency' => 'COP',
                'locale' => 'es-CO',
                'financial_level' => 'beginner',
            ],
            'summary' => [
                'period' => $startOfMonth->format('Y-m'),
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'savings_rate' => $savingsRate,
                'previous_savings_rate' => $previousSavingsRate,
            ],
            'categories' => $categories,
            'budgets' => $formattedBudgets,
            'top_tip' => $topTip ? $topTip->getTitle() . ': ' . $topTip->getShortDescription() : null,
        ];
    }

    /**
     * Sums all transaction amounts matching the given type.
     */
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

    /**
     * Groups expense transactions by category name.
     */
    private function groupExpensesByCategory(array $transactions): array
    {
        $spending = [];

        foreach ($transactions as $transaction) {
            if ($transaction->getType() !== 'gasto') {
                continue;
            }

            $categoryName = $transaction->getCategory() ? 
                $transaction->getCategory()->getName() : 'Sin categoría';
        
            if (!isset($spending[$categoryName])) {
                $spending[$categoryName] = 0.0;
            }
            $spending[$categoryName] += $transaction->getAmount();
        }
        
        return $spending;
    }

    /**
     * calculates the saving rate as a percentage.
     */
    private function calculateSavingRate(float $totalIncome, float $totalExpenses): float
    {
        if($totalIncome <= 0) 
            return 0.0;
        
        return round((($totalIncome - $totalExpenses) / $totalIncome) * 100, 2);
    }

    /**
     * Formats active budgets with actual spending per category
     */
    private function formatBudgets(
        array $budgets,
        array $categorySpending,
        \DateTime $startOfMonth,
        \DateTime $endOfMonth
    ): array
    {
        $formatted = [];

        foreach($budgets as $budget) {
            if ($budget->getEndDate() < $startOfMonth ||
                $budget->getStartDate() > $endOfMonth) {
                continue;
            }

            foreach ($budget->getBudgetCategories() as $budgetCategory) {
                $categoryName = $budgetCategory->getCategory()->getName();
                $limit = $budgetCategory->getAmount();
                $spent = $categorySpending[$categoryName] ?? 0.0;

                $formatted[] = [
                    'name' => $categoryName,
                    'limit' => $limit,
                    'spent' => $spent,
                ];
            }
        }
        return $formatted;
    }
}