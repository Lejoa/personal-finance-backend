<?php

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthService
{
    /** Number of days before a refresh token expires. */
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JWTTokenManagerInterface $jwtManager
    ) {
    }

    /**
     * Resolve or create the local User from a Google OAuth callback.
     *
     * Lookup order:
     *   1. Match by googleId (returning user).
     *   2. Match by email — link the Google account to an existing local user.
     *   3. Create a brand-new user.
     *
     * Avatar is refreshed on every login so profile picture changes are picked up.
     *
     * @param ClientRegistry $clientRegistry KnpU OAuth2 client registry used to exchange the code for user data
     *
     * @return array{user: User, accessToken: string, refreshToken: string}
     */
    public function handleGoogleCallback(ClientRegistry $clientRegistry): array
    {
        $client = $clientRegistry->getClient('google');
        $accessToken = $client->getAccessToken();

        /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();
        $name = $googleUser->getName();
        $avatar = $googleUser->getAvatar();

        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

        if (!$user) {
            $user = $this->resolveUserByEmail($email, $googleId, $name, $avatar);
        } elseif ($user->getAvatar() !== $avatar) {
            $user->setAvatar($avatar);
            $this->entityManager->flush();
        }

        $jwt = $this->jwtManager->create($user);
        $refreshToken = $this->issueRefreshToken($user);

        return [
            'user' => $user,
            'accessToken' => $jwt,
            'refreshToken' => $refreshToken,
        ];
    }

    /**
     * Issue a new JWT and a rotated refresh token in exchange for a valid refresh token string.
     *
     * The previous refresh token is immediately revoked to prevent replay attacks.
     * Returns null when the provided token string is not found, is expired, or is already revoked.
     *
     * @return array{accessToken: string, refreshToken: string}|null
     */
    public function refreshAccessToken(string $tokenString): ?array
    {
        $refreshToken = $this->refreshTokenRepository->findValidToken($tokenString);

        if (!$refreshToken) {
            return null;
        }

        $user = $refreshToken->getUser();

        $newAccessToken = $this->jwtManager->create($user);
        $newRefreshToken = $this->issueRefreshToken($user);

        $refreshToken->setIsRevoked(true);
        $this->entityManager->flush();

        return [
            'accessToken' => $newAccessToken,
            'refreshToken' => $newRefreshToken,
        ];
    }

    /**
     * Revoke a specific refresh token by its string value, then revoke all remaining tokens for the user.
     *
     * Revoking a single token first is a best-effort step; even if the token string is
     * already invalid the full-user revocation still runs so the session is always cleared.
     *
     * @param string|null $tokenString Raw refresh token from the request body (may be absent)
     * @param User|null $user Currently authenticated user (used for full-session revocation)
     */
    public function logout(?string $tokenString, ?User $user): void
    {
        if ($tokenString) {
            $refreshToken = $this->refreshTokenRepository->findValidToken($tokenString);

            if ($refreshToken) {
                $refreshToken->setIsRevoked(true);
                $this->entityManager->flush();
            }
        }

        if ($user) {
            $this->refreshTokenRepository->revokeAllUserTokens($user);
        }
    }

    /**
     * Find a user by email and link the Google account, or create a new user when no match exists.
     *
     * Linking by email lets users who registered via another method (e.g. email/password)
     * later sign in with Google without ending up with duplicate accounts.
     */
    private function resolveUserByEmail(string $email, string $googleId, string $name, ?string $avatar): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user) {
            $user->setGoogleId($googleId);
            $user->setAvatar($avatar);
        } else {
            $user = new User();
            $user->setEmail($email);
            $user->setGoogleId($googleId);
            $user->setName($name);
            $user->setAvatar($avatar);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Create, persist, and return the raw token string for a new refresh token.
     *
     * Uses 64 random bytes (128 hex chars) for the token value — sufficient entropy
     * to make brute-force and enumeration attacks computationally infeasible.
     */
    private function issueRefreshToken(User $user): string
    {
        $tokenString = bin2hex(random_bytes(64));

        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setToken($tokenString);
        $refreshToken->setExpiresAt((new \DateTime())->modify(\sprintf('+%d days', self::REFRESH_TOKEN_TTL_DAYS)));

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $tokenString;
    }
}
