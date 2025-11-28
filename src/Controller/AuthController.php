<?php

namespace App\Controller;

use App\Entity\User;
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
    public function googleConnect(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirige a Google OAuth con los scopes configurados
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

            // 7. Generar JWT propio para el usuario
            $jwt = $jwtManager->create($user);

            // 8. Redirigir al frontend con el JWT en la URL
            return new RedirectResponse("$frontendUrl/auth/callback?token=$jwt");

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
}
