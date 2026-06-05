<?php

namespace App\Repository;

use App\Entity\Tip;
use App\Entity\TipReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TipReference>
 */
class TipReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TipReference::class);
    }

    /**
     * @return TipReference[]
     */
    public function findByTip(Tip $tip): array
    {
        return $this->findBy(['tip' => $tip]);
    }
}
