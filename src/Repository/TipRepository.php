<?php

namespace App\Repository;

use App\Entity\Tip;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tip>
 */
class TipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tip::class);
    }


    /**
     * Returns up to $limit tips whose tags field contains at least one of the given tags.
     * Tags are stored as a comma-separated string (e.g. "ahorro,inversión,gastos").
     *
     * @param string[] $tags
     *
     * @return Tip[]
     */
    public function findByTags(array $tags, int $limit = 3): array
    {
        if (empty($tags)) {
            return [];
        }

        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit);

        $orConditions = $qb->expr()->orX();
        foreach ($tags as $index => $tag) {
            $param = 'tag_' . $index;
            $orConditions->add($qb->expr()->like('t.tags', ':' . $param));
            $qb->setParameter($param, '%' . $tag . '%');
        }

        $qb->andWhere($orConditions);

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns the most recently created tips as a fallback.
     *
     * @return Tip[]
     */
    public function findRecent(int $limit = 3): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }
}
