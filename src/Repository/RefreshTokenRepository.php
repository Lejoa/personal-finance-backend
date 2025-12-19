<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repositorio para la entidad RefreshToken
 * Proporciona métodos para buscar y gestionar tokens de refresco
 * 
 */
class RefreshTokenRepository extends ServiceEntityRepository {
    
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * Buscar refresh token por su valor
     */
    public function findValidToken(string $token): ?RefreshToken {
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
     * Revocar todos los tokens de refresco de un usuario
     */
    public function revokeAllUserTokens(User $user): void {
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
     * Eliminar tokens expirados (para limpieza periódica)
     */
    public function deleteExpiredTokens(): int {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Eliminar tokens revocados (para limpieza periódica)
     */
    public function deleteOldRevokedTokens(int $daysOld = 15): int {
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