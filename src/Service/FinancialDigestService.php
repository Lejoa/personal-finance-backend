<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\TransactionRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FinancialDigestService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly CacheInterface $financialDigestCache
    ) {
    }

    /**
     * Returns the enriched financial digest for the user.
     * Cached for 30 minutes, invalidated on transaction changes.
     */
    public function getDigest(User $user): array
    {
        $key = $this->cacheKey($user);

        return $this->financialDigestCache->get($key, function (ItemInterface $item) use ($user) {
            $item->expiresAfter(1800); // 30 minutes

            return $this->computeDigest($user);
        });
    }

    /**
     * Invalidates the cached digest for the user (call after any transaction change).
     */
    public function invalidate(User $user): void
    {
        $this->financialDigestCache->delete($this->cacheKey($user));
    }

    /**
     * Computes the enriched financial level based on user behavior signals.
     * Returns: "principiante", "intermedio", or "avanzado".
     */
    public function computeFinancialLevel(User $user, array $digest): string
    {
        $score = 0;

        // Account age
        $createdAt = $user->getCreatedAt() ?? new \DateTime();
        $daysSinceCreation = (new \DateTime())->diff($createdAt)->days;
        if ($daysSinceCreation >= 90) {
            $score += 2;
        } elseif ($daysSinceCreation >= 30) {
            ++$score;
        }

        // Transaction consistency: months with 5+ transactions in last 3 months
        $consistentMonths = $digest['consistent_months'] ?? 0;
        if ($consistentMonths >= 3) {
            $score += 2;
        } elseif ($consistentMonths >= 2) {
            ++$score;
        }

        // Budget usage
        $budgetHealth = $digest['budget_health'] ?? [];
        if (!empty($budgetHealth)) {
            ++$score;
            $allUnder = array_filter($budgetHealth, static fn ($b) => ($b['pct_used'] ?? 0) < 100);
            if (\count($allUnder) === \count($budgetHealth)) {
                ++$score;
            }
        }

        // Savings improving
        if (($digest['savings_improving'] ?? false) === true) {
            ++$score;
        }

        // Category diversity
        $categoryCount = \count($digest['category_trends'] ?? []);
        if ($categoryCount >= 5) {
            $score += 2;
        } elseif ($categoryCount >= 3) {
            ++$score;
        }

        if ($score >= 7) {
            return 'avanzado';
        }
        if ($score >= 4) {
            return 'intermedio';
        }

        return 'principiante';
    }

    private function cacheKey(User $user): string
    {
        return 'financial_digest_' . $user->getId();
    }

    private function computeDigest(User $user): array
    {
        $now = new \DateTime();
        $startOfMonth = new \DateTime('first day of this month');
        $startOfMonth->setTime(0, 0, 0);
        $endOfMonth = new \DateTime('last day of this month');
        $endOfMonth->setTime(23, 59, 59);
        $dayOfMonth = (int) $now->format('j');
        $daysInMonth = (int) $now->format('t');
        $daysRemaining = $daysInMonth - $dayOfMonth;

        // Current month transactions
        $transactions = $this->transactionRepository->findByFilters(
            $user,
            null,
            $startOfMonth,
            $endOfMonth
        );

        $totalExpenses = 0.0;
        $totalIncome = 0.0;
        $categoryCurrentMonth = [];

        foreach ($transactions as $t) {
            if ('gasto' === $t->getType()) {
                $totalExpenses += $t->getAmount();
                $cat = $t->getCategory() ? $t->getCategory()->getName() : 'Sin categoría';
                $categoryCurrentMonth[$cat] = ($categoryCurrentMonth[$cat] ?? 0.0) + $t->getAmount();
            } else {
                $totalIncome += $t->getAmount();
            }
        }

        // Previous month totals
        $previousTotals = $this->transactionRepository->getPreviousMonthTotals($user);
        $prevIncome = $previousTotals['income'];
        $prevExpenses = $previousTotals['expenses'];
        $prevSavingsRate = $prevIncome > 0
            ? round((($prevIncome - $prevExpenses) / $prevIncome) * 100, 2)
            : 0.0;

        $currentSavingsRate = $totalIncome > 0
            ? round((($totalIncome - $totalExpenses) / $totalIncome) * 100, 2)
            : 0.0;

        // Spending velocity and projection
        $spendingVelocity = $dayOfMonth > 0 ? round($totalExpenses / $dayOfMonth, 2) : 0.0;
        $projectedExpenses = round($spendingVelocity * $daysInMonth, 2);

        // Category trends: compare current month vs 3-month average
        $categoryTrends = [];
        foreach ($categoryCurrentMonth as $catName => $currentAmount) {
            $categoryTrends[] = [
                'name' => $catName,
                'current_month' => $currentAmount,
                'avg_3_months' => 0.0,
                'delta_pct' => 0.0,
            ];
        }

        // Enrich with historical averages using getMonthlyCategorySpending per category
        $categories = $this->transactionRepository->getExpenseCategoryIds($user);
        $historicalMap = [];
        foreach ($categories as $row) {
            $catId = $row['id'];
            $catName = $row['name'];
            $history = $this->transactionRepository->getMonthlyCategorySpending($user, $catId, 3);
            if (!empty($history)) {
                $avg = array_sum(array_column($history, 'total')) / \count($history);
                $historicalMap[$catName] = round($avg, 2);
            }
        }

        $categoryTrends = [];
        arsort($categoryCurrentMonth);
        foreach ($categoryCurrentMonth as $catName => $currentAmount) {
            $avg = $historicalMap[$catName] ?? 0.0;
            $deltaPct = $avg > 0 ? round((($currentAmount - $avg) / $avg) * 100, 1) : 0.0;

            $categoryTrends[] = [
                'name' => $catName,
                'current_month' => $currentAmount,
                'avg_3_months' => $avg,
                'delta_pct' => $deltaPct,
            ];
        }

        // Budget health
        $budgets = $this->budgetRepository->findBy(['user' => $user]);
        $budgetHealth = [];
        foreach ($budgets as $budget) {
            if ($budget->getEndDate() < $startOfMonth || $budget->getStartDate() > $endOfMonth) {
                continue;
            }
            foreach ($budget->getBudgetCategories() as $bc) {
                $catName = $bc->getCategory()->getName();
                $limit = $bc->getAmount();
                $spent = $categoryCurrentMonth[$catName] ?? 0.0;
                $pctUsed = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0.0;

                $budgetHealth[] = [
                    'name' => $catName,
                    'limit' => $limit,
                    'spent' => $spent,
                    'pct_used' => $pctUsed,
                    'days_remaining' => $daysRemaining,
                ];
            }
        }

        // Anomalies: categories spending > 150% of 3-month average
        $anomalies = array_filter(
            $categoryTrends,
            static fn ($t) => $t['avg_3_months'] > 0 && $t['delta_pct'] > 50.0
        );

        // Consistent months: count months in last 3 with at least 5 transactions
        $consistentMonths = $this->transactionRepository->countConsistentMonths($user, 3, 5);

        // Savings improving flag
        $savingsImproving = $currentSavingsRate > $prevSavingsRate;

        return [
            'spending_velocity' => $spendingVelocity,
            'projected_expenses' => $projectedExpenses,
            'previous_savings_rate' => $prevSavingsRate,
            'savings_improving' => $savingsImproving,
            'category_trends' => array_values($categoryTrends),
            'budget_health' => $budgetHealth,
            'anomalies' => array_values($anomalies),
            'consistent_months' => $consistentMonths,
            'days_remaining' => $daysRemaining,
        ];
    }
}
