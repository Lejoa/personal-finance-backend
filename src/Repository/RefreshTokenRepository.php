<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for the RefreshToken entity.
 * Provides methods to look up and manage refresh tokens.
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * Find a non-expired, non-revoked refresh token by its raw token string.
     */
    public function findValidToken(string $token): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.token = :token')
            ->andWhere('rt.expiresAt > :now')
            ->andWhere('rt.isRevoked = false')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Bulk-revoke every active refresh token belonging to the given user.
     */
    public function revokeAllUserTokens(User $user): void
    {
        $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.isRevoked', ':isRevoked')
            ->where('rt.user = :user')
            ->setParameter('isRevoked', true)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all expired tokens. Intended for periodic housekeeping jobs.
     */
    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Delete revoked tokens older than the given number of days. Intended for periodic housekeeping jobs.
     */
    public function deleteOldRevokedTokens(int $daysOld = 15): int
    {
        $date = new \DateTime();
        $date->modify("-$daysOld days");

        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.isRevoked = true')
            ->andWhere('rt.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
