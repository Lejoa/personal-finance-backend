<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Returns a transaction by ID only if it belongs to the given user.
     * Performs the ownership check at SQL level to prevent IDOR.
     */
    public function findByIdForUser(int $id, User $user): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->where('t.id = :id')
            ->andWhere('t.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find transactions with filters: type, date range and limit.
     *
     * @return Transaction[]
     */
    public function findByFilters(
        User $user,
        ?string $type = null,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?int $limit = null,
        ?string $synchronized = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.date', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if (null !== $type) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if (null !== $startDate) {
            $qb->andWhere('t.date >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if (null !== $endDate) {
            $qb->andWhere('t.date <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if (null !== $synchronized) {
            $qb->andWhere('t.synchronized = :synchronized')
               ->setParameter('synchronized', $synchronized);
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find an existing transaction by exact note match for the given user.
     * Used for SMS deduplication: the SMS body is unique per banking event.
     */
    public function findExistingByNote(User $user, string $note): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.note = :note')
            ->setParameter('user', $user)
            ->setParameter('note', $note)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns monthly spending totals for a specific category over the last N months (excluding current month).
     * Used to calculate historical averages for formative feedback.
     *
     * @return array<int, array{month: string, total: float}>
     */
    public function getMonthlyCategorySpending(User $user, int $categoryId, int $monthsBack = 3): array
    {
        $startDate = new \DateTime("first day of -{$monthsBack} months");
        $startDate->setTime(0, 0, 0);

        $endDate = new \DateTime('first day of this month');
        $endDate->setTime(0, 0, 0);

        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT TO_CHAR(t.date, 'YYYY-MM') AS month, SUM(t.amount) AS total
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :userId
              AND c.id = :categoryId
              AND t.type = 'gasto'
              AND t.date >= :startDate
              AND t.date < :endDate
            GROUP BY TO_CHAR(t.date, 'YYYY-MM')
            ORDER BY month ASC
        ";

        $rows = $conn->fetchAllAssociative($sql, [
            'userId' => $user->getId(),
            'categoryId' => $categoryId,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ]);

        return array_map(
            static fn ($row) => ['month' => $row['month'], 'total' => (float) $row['total']],
            $rows
        );
    }

    /**
     * Returns total spending for a specific category in the current month.
     */
    public function getCurrentMonthCategorySpending(User $user, int $categoryId): float
    {
        $startDate = new \DateTime('first day of this month');
        $startDate->setTime(0, 0, 0);

        $endDate = new \DateTime('last day of this month');
        $endDate->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as total')
            ->join('t.category', 'c')
            ->where('t.user = :user')
            ->andWhere('c.id = :categoryId')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :startDate')
            ->andWhere('t.date <= :endDate')
            ->setParameter('user', $user)
            ->setParameter('categoryId', $categoryId)
            ->setParameter('type', 'gasto')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0.0);
    }

    /**
     * Returns total income for the current month.
     */
    public function getCurrentMonthIncome(User $user): float
    {
        $startDate = new \DateTime('first day of this month');
        $startDate->setTime(0, 0, 0);

        $endDate = new \DateTime('last day of this month');
        $endDate->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount) as total')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :startDate')
            ->andWhere('t.date <= :endDate')
            ->setParameter('user', $user)
            ->setParameter('type', 'ingreso')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0.0);
    }

    /**
     * Returns income and expenses for the previous calendar month.
     *
     * @return array{income: float, expenses: float}
     */
    public function getPreviousMonthTotals(User $user): array
    {
        $start = new \DateTime('first day of last month');
        $start->setTime(0, 0, 0);
        $end = new \DateTime('last day of last month');
        $end->setTime(23, 59, 59);

        $rows = $this->createQueryBuilder('t')
            ->select('t.type, SUM(t.amount) as total')
            ->where('t.user = :user')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->groupBy('t.type')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $income = 0.0;
        $expenses = 0.0;
        foreach ($rows as $row) {
            if ('ingreso' === $row['type']) {
                $income = (float) $row['total'];
            } elseif ('gasto' === $row['type']) {
                $expenses = (float) $row['total'];
            }
        }

        return ['income' => $income, 'expenses' => $expenses];
    }

    /**
     * Returns distinct expense category ids and names for a user (current month).
     * Used to iterate and fetch 3-month history per category.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getExpenseCategoryIds(User $user): array
    {
        $startOfMonth = new \DateTime('first day of this month');
        $startOfMonth->setTime(0, 0, 0);
        $endOfMonth = new \DateTime('last day of this month');
        $endOfMonth->setTime(23, 59, 59);

        $rows = $this->createQueryBuilder('t')
            ->select('DISTINCT c.id, c.name')
            ->join('t.category', 'c')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('type', 'gasto')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getResult();

        return array_map(static fn ($row) => ['id' => $row['id'], 'name' => $row['name']], $rows);
    }

    /**
     * Counts how many of the last N months had at least $minTransactions transactions.
     * Used to compute the user's financial consistency score.
     */
    public function countConsistentMonths(User $user, int $monthsBack = 3, int $minTransactions = 5): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT TO_CHAR(t.date, 'YYYY-MM') AS month, COUNT(*) AS tx_count
            FROM transactions t
            WHERE t.user_id = :userId
              AND t.date >= :startDate
              AND t.date < :endDate
            GROUP BY TO_CHAR(t.date, 'YYYY-MM')
        ";

        $startDate = new \DateTime("first day of -{$monthsBack} months");
        $endDate = new \DateTime('first day of this month');

        $rows = $conn->fetchAllAssociative($sql, [
            'userId' => $user->getId(),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ]);

        return \count(array_filter($rows, static fn ($r) => (int) $r['tx_count'] >= $minTransactions));
    }

    /**
     * Returns aggregated financial data for the last 30 days:
     * total income, total expenses, and top 3 expense category names.
     *
     * @return array{total_income: float, total_expenses: float, top_expense_categories: string[]}
     */
    public function getUserFinancialSummary(User $user): array
    {
        $since = new \DateTime('-30 days');

        $totals = $this->createQueryBuilder('t')
            ->select('t.type, SUM(t.amount) as total')
            ->where('t.user = :user')
            ->andWhere('t.date >= :since')
            ->groupBy('t.type')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $totalIncome = 0.0;
        $totalExpenses = 0.0;
        foreach ($totals as $row) {
            if ('ingreso' === $row['type']) {
                $totalIncome = (float) $row['total'];
            } elseif ('gasto' === $row['type']) {
                $totalExpenses = (float) $row['total'];
            }
        }

        $topCategories = $this->createQueryBuilder('t')
            ->select('c.name, SUM(t.amount) as total')
            ->join('t.category', 'c')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.date >= :since')
            ->groupBy('c.name')
            ->orderBy('total', 'DESC')
            ->setMaxResults(3)
            ->setParameter('user', $user)
            ->setParameter('type', 'gasto')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $topExpenseCategories = array_map(
            static fn ($row) => mb_strtolower($row['name']),
            $topCategories
        );

        return [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'top_expense_categories' => $topExpenseCategories,
        ];
    }

    /**
     * Returns income, expenses and savings rate aggregated by month for an arbitrary range.
     *
     * $from and $to are YYYY-MM strings already validated by PeriodHint before reaching here.
     * Lexicographic comparison works correctly with this format, so BETWEEN is safe.
     *
     * Uses raw SQL (as getMonthlyCategorySpending and countConsistentMonths do) because
     * TO_CHAR(date, 'YYYY-MM') is not available in Doctrine DQL without an extension.
     *
     * @return array<int, array{month: string, income: float, expenses: float, savings_rate: float}>
     */
    public function getMonthlyTotalsForRange(User $user, string $from, string $to): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT
                TO_CHAR(t.date, 'YYYY-MM') AS month,
                SUM(CASE WHEN t.type = 'ingreso' THEN t.amount ELSE 0 END)::float AS income,
                SUM(CASE WHEN t.type = 'gasto'   THEN t.amount ELSE 0 END)::float AS expenses
            FROM transactions t
            WHERE t.user_id = :userId
              AND TO_CHAR(t.date, 'YYYY-MM') >= :from
              AND TO_CHAR(t.date, 'YYYY-MM') <= :to
            GROUP BY TO_CHAR(t.date, 'YYYY-MM')
            ORDER BY month ASC
        ";

        $rows = $conn->fetchAllAssociative($sql, [
            'userId' => $user->getId(),
            'from'   => $from,
            'to'     => $to,
        ]);

        return array_map(function (array $row): array {
            $income   = (float) $row['income'];
            $expenses = (float) $row['expenses'];
            $savings  = $income > 0
                ? round((($income - $expenses) / $income) * 100, 1)
                : 0.0;

            return [
                'month'        => $row['month'],
                'income'       => $income,
                'expenses'     => $expenses,
                'savings_rate' => $savings,
            ];
        }, $rows);
    }

    /**
     * Returns expenses by category and month for an arbitrary range.
     *
     * Optionally filters by category name using ILIKE for case-insensitive matching,
     * tolerating capitalisation variations the LLM may produce.
     * user_id is always applied first to leverage the index on the date column.
     *
     * @return array<int, array{month: string, category: string, total: float}>
     */
    public function getMonthlyCategorySpendingForRange(
        User $user,
        string $from,
        string $to,
        ?string $categoryName = null,
    ): array {
        $conn   = $this->getEntityManager()->getConnection();
        $params = [
            'userId' => $user->getId(),
            'from'   => $from,
            'to'     => $to,
        ];

        $categoryFilter = '';
        if (null !== $categoryName) {
            $categoryFilter        = 'AND c.name ILIKE :categoryName';
            $params['categoryName'] = '%' . $categoryName . '%';
        }

        $sql = "
            SELECT
                TO_CHAR(t.date, 'YYYY-MM') AS month,
                c.name AS category,
                SUM(t.amount)::float AS total
            FROM transactions t
            INNER JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :userId
              AND t.type = 'gasto'
              AND TO_CHAR(t.date, 'YYYY-MM') >= :from
              AND TO_CHAR(t.date, 'YYYY-MM') <= :to
              {$categoryFilter}
            GROUP BY TO_CHAR(t.date, 'YYYY-MM'), c.name
            ORDER BY month ASC, total DESC
        ";

        $rows = $conn->fetchAllAssociative($sql, $params);

        return array_map(
            static fn (array $row): array => [
                'month'    => $row['month'],
                'category' => $row['category'],
                'total'    => (float) $row['total'],
            ],
            $rows,
        );
    }

    /**
     * Returns distinct YYYY-MM months for which the user has at least one transaction,
     * strictly before $beforeMonth (exclusive), ordered ascending.
     *
     * Used to discover historical months eligible for analysis backfill — the boundary
     * is expressed in the SQL WHERE clause (not filtered in PHP after fetching) so that
     * the current, still-open month is never pulled from the DB at all.
     *
     * @return string[] e.g. ['2025-01', '2025-02', '2025-03']
     */
    public function getDistinctMonthsBefore(User $user, string $beforeMonth): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT DISTINCT TO_CHAR(t.date, 'YYYY-MM') AS month
            FROM transactions t
            WHERE t.user_id = :userId
              AND TO_CHAR(t.date, 'YYYY-MM') < :beforeMonth
            ORDER BY month ASC
        ";

        $rows = $conn->fetchAllAssociative($sql, [
            'userId' => $user->getId(),
            'beforeMonth' => $beforeMonth,
        ]);

        return array_map(static fn (array $row): string => $row['month'], $rows);
    }
}
