<?php

namespace App\Controller;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    /**
     * Inicia el flujo de autenticación con Google OAuth
     * Redirige al usuario a la página de login de Google
     */
    #[Route('/auth/google', name: 'auth_google')]
    public function googleConnect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        // Si el cliente es Android, guardarlo en la sesión antes de redirigir a Google.
        // No se puede usar el campo "state" porque knpu lo usa internamente para CSRF.
        // La sesión persiste entre la request /auth/google y el callback /auth/google/callback
        // ya que ambas requests vienen del mismo browser (Chrome Custom Tab del celular).
        if ($request->query->get('client') === 'android') {
            $request->getSession()->set('oauth_client', 'android');
        }

        return $clientRegistry
            ->getClient('google')
            ->redirect(
                ['openid', 'email', 'profile'], // Scopes solicitados
                []
            );
    }

    /**
     * Callback de Google OAuth
     * Procesa la respuesta de Google, crea/actualiza el usuario y genera JWT
     */
    #[Route('/auth/google/callback', name: 'auth_google_callback')]
    public function googleCallback(
        Request $request,
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager
    ): RedirectResponse {
        // Obtener URL del frontend desde variables de entorno
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:3000';

        try {
            // 1. Obtener el cliente OAuth de Google
            $client = $clientRegistry->getClient('google');

            // 2. Obtener el access token desde Google
            $accessToken = $client->getAccessToken();

            // 3. Obtener datos del usuario desde Google usando el access token
            /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
            $googleUser = $client->fetchUserFromToken($accessToken);

            // 4. Extraer información del usuario de Google
            $googleId = $googleUser->getId();
            $email = $googleUser->getEmail();
            $name = $googleUser->getName();
            $avatar = $googleUser->getAvatar();

            // 5. Buscar si el usuario ya existe en nuestra base de datos
            $user = $userRepository->findOneBy(['googleId' => $googleId]);

            if (!$user) {
                // 6. Si no existe, verificar si existe un usuario con el mismo email
                $existingUser = $userRepository->findOneBy(['email' => $email]);

                if ($existingUser) {
                    // Si existe un usuario con el mismo email, vincular la cuenta de Google
                    $user = $existingUser;
                    $user->setGoogleId($googleId);
                    $user->setAvatar($avatar);
                } else {
                    // Si no existe, crear nuevo usuario
                    $user = new User();
                    $user->setEmail($email);
                    $user->setGoogleId($googleId);
                    $user->setName($name);
                    $user->setAvatar($avatar);
                }

                $entityManager->persist($user);
                $entityManager->flush();
            } else {
                // Si el usuario ya existe, actualizar su avatar por si cambió
                if ($user->getAvatar() !== $avatar) {
                    $user->setAvatar($avatar);
                    $entityManager->flush();
                }
            }

            // 7. Generar JWT (access token )propio para el usuario
            $accessToken = $jwtManager->create($user);
            $refreshToken = $this->generateRefreshToken(
                $user, 
                $entityManager
            );

            // 8. Redirigir al frontend con ambos tokens en la URL.
            // Si el client es Android (custon URL scheme), redirigir a personalfinance://
            // para que Android intercepte la URL y la enrute de cuelta a la app nativa.
            // El parámetro ?client=android es enviado por AuthService.loginWithGoogleNative()
            
            // Recuperar el indicador de cliente Android desde la sesión.
            // Chrome Custom Tab comparte la sesión con la request inicial /auth/google,
            // por lo que el valor guardado en sesión está disponible en el callback.
            $isAndroidClient = $request->getSession()->get('oauth_client') === 'android';
            $request->getSession()->remove('oauth_client');
            $callbackBase = $isAndroidClient
                ? ($_ENV['FRONTEND_URL_ANDROID'] ?? 'personalfinance://auth/callback')
                : "$frontendUrl/auth/callback";
                
            return new RedirectResponse("$callbackBase?token=$accessToken&refreshToken=$refreshToken");

        } catch (\Exception $e) {
            // En caso de error, redirigir al frontend con mensaje de error
            return new RedirectResponse("$frontendUrl/login?error=oauth_failed&message=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Endpoint para obtener información del usuario autenticado
     * Requiere JWT válido en el header Authorization
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        // Obtener usuario autenticado desde el token JWT
        $user = $this->getUser();

        // Verificar que el usuario esté autenticado
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Not authenticated',
                'message' => 'You must be logged in to access this endpoint'
            ], 401);
        }

        // Retornar información del usuario
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Endpoint para renovar el access token usando un refresh token
     * Permite al cliente obtener un nuevo JWT sin re-autenticarse
     */
    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refreshToken(
        Request $request,
        RefreshTokenRepository $refreshTokenRepository,
        JWTTokenManagerInterface $jwtManager,
        EntityManagerInterface $entityManager
    ): JsonResponse {

        //Obtener refresh token del body de la petición
        $datas = json_decode($request->getContent(), true);
        $refreshTokenString = $datas['refreshToken'] ?? null;

        if (!$refreshTokenString) {
            return $this->json([
                'error' => 'Missing refresh token',
                'message' => 'Refresh token is required'
            ], 400);
        }

        // Buscar el refresh token en la base de datos
        $refreshToken = $refreshTokenRepository->findValidToken($refreshTokenString);

        if(!$refreshToken) {
            return $this->json([
                'error' => 'Invalid refresh token',
                'message' => 'The provided refresh token is invalid or expired'
            ], 401);
        }

        // Obtener usuario asociado al refresh token
        $user = $refreshToken->getUser();

        // Generar nuevo acces token 
        $newAccessToken = $jwtManager->create($user);

        // Opcional: Generar nuevo refresh token (rotación de refresh tokens)
        // Esto es más seguro pero requiere que el cliente maneje el nuevo refresh token
        $newRefreshToken = $this->generateRefreshToken($user, $entityManager);

        // Revocar el refresh token anterior (rotación)
        $refreshToken->setIsRevoked(true);
        $entityManager->flush();

        // Retornar nuevos tokens
        return $this->json([
            'accessToken' => $newAccessToken,
            'refreshToken' => $newRefreshToken,
            'tokenType' => 'bearer',
            'expiresIn' => 3600 // 1 hora
        ]);
    }

    /**
     * Endpoint para hacer logout y revocar el refresh token
     */
    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(
        Request $request,
        RefreshTokenRepository $refreshTokenRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Obtener refresh token del body de la petición
        $data = json_decode($request->getContent(), true);
        $refreshTokenString = $data['refreshToken'] ?? null;

        if ($refreshTokenString) {
            // Buscar y revocar el refresh token
            $refreshToken = $refreshTokenRepository->findValidToken($refreshTokenString);

            if ($refreshToken) {
                $refreshToken->setIsRevoked(true);
                $entityManager->flush();
            }
        }

        // También podemos revocar todos los tokens del usuario autenticado
        $user = $this->getUser();
        if ($user instanceof User) {
            $refreshTokenRepository->revokeAllUserTokens($user);
        }

        // Retornar respuesta exitosa
        return $this->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Generar un nuevo refresh token para un usuario
     * 
     * @param User $user Usuario para el cual generar el refresh token
     * @param EntityManagerInterface $entityManager Entity manager para persistir el token
     * @return string El refresh token generado
     */
    private function generateRefreshToken(
        User $user,
        EntityManagerInterface $entityManager): string {

            // Generar token aleatorio seguro
            $tokenString = bin2hex(random_bytes(64));

            // Crear entidad RefreshToken
            $refreshToken = new RefreshToken();
            $refreshToken->setUser($user);
            $refreshToken->setToken($tokenString);

            // Establecer fecha de expiración (ej. 30 días desde ahora)
            $expiresAt = new \DateTime();
            $expiresAt->modify('+30 days');
            $refreshToken->setExpiresAt($expiresAt);

            // Persistir en la base de datos
            $entityManager->persist($refreshToken);
            $entityManager->flush();

            return $tokenString;
        }
}
