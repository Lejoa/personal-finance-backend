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
            ->orderBy('t.createdAt', 'DESC');

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
}
