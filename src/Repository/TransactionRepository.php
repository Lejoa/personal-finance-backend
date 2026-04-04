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
     * Find transactions with filters: type, date range and limit
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

        if ($type !== null) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $type);
        }

        if ($startDate !== null) {
            $qb->andWhere('t.date >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('t.date <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        if ($synchronized !== null) {
            $qb->andWhere('t.synchronized = :synchronized')
               ->setParameter('synchronized', $synchronized);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
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
            'userId'     => $user->getId(),
            'categoryId' => $categoryId,
            'startDate'  => $startDate->format('Y-m-d'),
            'endDate'    => $endDate->format('Y-m-d'),
        ]);

        return array_map(
            fn($row) => ['month' => $row['month'], 'total' => (float) $row['total']],
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
            if ($row['type'] === 'ingreso') {
                $totalIncome = (float) $row['total'];
            } elseif ($row['type'] === 'gasto') {
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
            fn($row) => mb_strtolower($row['name']),
            $topCategories
        );

        return [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'top_expense_categories' => $topExpenseCategories,
        ];
    }
}
