<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    /**
     * Injects only AuthService — all OAuth, JWT, and token logic lives there.
     */
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    /**
     * Start the Google OAuth flow by redirecting the user to the Google consent screen.
     *
     * When the request comes from an Android client (Chrome Custom Tab), the query
     * parameter ?client=android is stored in the session so the callback can issue a
     * deep-link redirect instead of a web URL. The session is used instead of the OAuth
     * "state" parameter because KnpU reserves "state" for its own CSRF protection.
     */
    #[Route('/auth/google', name: 'auth_google')]
    public function googleConnect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        if ('android' === $request->query->get('client')) {
            $request->getSession()->set('oauth_client', 'android');
        }

        return $clientRegistry
            ->getClient('google')
            ->redirect(['openid', 'email', 'profile'], []);
    }

    /**
     * Handle the Google OAuth callback, resolve the local user, and issue JWT + refresh token.
     *
     * On success the user is redirected to the frontend with both tokens in the query string.
     * Android clients receive a deep-link URL (personalfinance://...) so the native app can
     * intercept the redirect via a Chrome Custom Tab intent filter. On any OAuth error the
     * user is redirected to the login page with an error description.
     */
    #[Route('/auth/google/callback', name: 'auth_google_callback')]
    public function googleCallback(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:3000';

        try {
            $result = $this->authService->handleGoogleCallback($clientRegistry);

            $isAndroidClient = 'android' === $request->getSession()->get('oauth_client');
            $request->getSession()->remove('oauth_client');

            $callbackBase = $isAndroidClient
                ? ($_ENV['FRONTEND_URL_ANDROID'] ?? 'personalfinance://auth/callback')
                : "$frontendUrl/auth/callback";

            return new RedirectResponse(
                "$callbackBase?token={$result['accessToken']}&refreshToken={$result['refreshToken']}"
            );
        } catch (\Exception $e) {
            return new RedirectResponse(
                "$frontendUrl/login?error=oauth_failed&message=" . urlencode($e->getMessage())
            );
        }
    }

    /**
     * Return the authenticated user's profile.
     *
     * Requires a valid JWT in the Authorization header. Returns 401 when the
     * request is unauthenticated or the resolved principal is not a User entity.
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                ['error' => 'Not authenticated', 'message' => 'You must be logged in to access this endpoint'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Exchange a valid refresh token for a new JWT and a rotated refresh token.
     *
     * Rotation invalidates the previous refresh token immediately, so each refresh
     * token can only be used once. Returns 400 when the body is missing the token,
     * and 401 when the token is not found, expired, or already revoked.
     */
    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $tokenString = $body['refreshToken'] ?? null;

        if (!$tokenString) {
            return $this->json(
                ['error' => 'Missing refresh token', 'message' => 'refreshToken is required in the request body'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $tokens = $this->authService->refreshAccessToken($tokenString);

        if (!$tokens) {
            return $this->json(
                ['error' => 'Invalid refresh token', 'message' => 'The provided refresh token is invalid or expired'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'accessToken' => $tokens['accessToken'],
            'refreshToken' => $tokens['refreshToken'],
            'tokenType' => 'bearer',
            'expiresIn' => 3600,
        ]);
    }

    /**
     * Revoke the provided refresh token and invalidate all active sessions for the user.
     *
     * The refreshToken field in the body is optional — even if absent, all server-side
     * sessions for the authenticated user are revoked. Always returns 200 so the client
     * can treat the logout as idempotent regardless of token validity.
     */
    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $tokenString = $body['refreshToken'] ?? null;

        $user = $this->getUser();
        $this->authService->logout($tokenString, $user instanceof User ? $user : null);

        return $this->json(['message' => 'Successfully logged out']);
    }
}
