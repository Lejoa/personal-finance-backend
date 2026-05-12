<?php

namespace App\Service;

use App\DTO\DevTokenRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class DevAuthService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly JWTTokenManagerInterface $jwtManager
    ) {
    }

    /**
     * Generate JWT token for development user.
     */
    public function generateDevToken(DevTokenRequest $dto): array
    {
        $user = $this->userRepository->findOneBy(['email' => $dto->email]);

        if (!$user) {
            $user = $this->createDevUser($dto);
        }

        $token = $this->jwtManager->create($user);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ],
        ];
    }

    /**
     * Create a development user.
     */
    private function createDevUser(DevTokenRequest $dto): User
    {
        $user = new User();
        $user->setEmail($dto->email);
        $user->setGoogleId('dev_' . uniqid());
        $user->setName($dto->name ?? 'Dev User');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
