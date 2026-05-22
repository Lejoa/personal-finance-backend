<?php

namespace App\Tests\Unit\Controller;

use App\Controller\AuthController;
use App\Entity\User;
use App\Service\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Serializer\SerializerInterface;

class AuthControllerTest extends TestCase
{
    private AuthService&MockObject $authService;
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->authService = $this->createMock(AuthService::class);
        $this->controller = new AuthController(
            $this->authService,
            'http://localhost:4200',
            'personalfinance://auth/callback',
        );
        $this->injectContainer($this->controller, null);
    }

    // ── me ───────────────────────────────────────────────────────────────────

    public function testMeReturns200WhenUserIsAuthenticated(): void
    {
        $user = $this->makeUser();
        $this->injectContainer($this->controller, $user);

        $response = $this->controller->me();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['id']);
        $this->assertSame('test@example.com', $data['email']);
    }

    public function testMeReturns401WhenUserIsNull(): void
    {
        $this->injectContainer($this->controller, null);

        $response = $this->controller->me();

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Not authenticated', $data['error']);
    }

    // ── refreshToken ─────────────────────────────────────────────────────────

    public function testRefreshTokenReturns400WhenBodyMissingRefreshToken(): void
    {
        $response = $this->controller->refreshToken($this->makeRequest([]));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Missing refresh token', $data['error']);
    }

    public function testRefreshTokenReturns401WhenTokenInvalid(): void
    {
        $this->authService->method('refreshAccessToken')->willReturn(null);

        $response = $this->controller->refreshToken($this->makeRequest(['refreshToken' => 'bad-token']));

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid refresh token', $data['error']);
    }

    public function testRefreshTokenReturns200AndRotatesToken(): void
    {
        $this->authService->method('refreshAccessToken')->willReturn([
            'accessToken' => 'new-access-token',
            'refreshToken' => 'new-refresh-token',
        ]);

        $response = $this->controller->refreshToken($this->makeRequest(['refreshToken' => 'valid-token']));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('new-access-token', $data['accessToken']);
        $this->assertSame('new-refresh-token', $data['refreshToken']);
        $this->assertSame('bearer', $data['tokenType']);
        $this->assertSame(3600, $data['expiresIn']);
    }

    // ── logout ───────────────────────────────────────────────────────────────

    public function testLogoutReturns200EvenWithoutRefreshToken(): void
    {
        $this->authService->expects($this->once())->method('logout')->with(null, null);

        $response = $this->controller->logout($this->makeRequest([]));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Successfully logged out', $data['message']);
    }

    public function testLogoutRevokesTokenWhenProvided(): void
    {
        $user = $this->makeUser();
        $this->injectContainer($this->controller, $user);

        $this->authService->expects($this->once())->method('logout')->with('some-token', $user);

        $this->controller->logout($this->makeRequest(['refreshToken' => 'some-token']));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeUser(int $id = 1, string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setName('Test User');

        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    private function makeRequest(array $body = []): Request
    {
        $request = new Request([], [], [], [], [], [], json_encode($body));
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    private function injectContainer(AuthController $controller, ?User $user): void
    {
        $tokenStorage = new TokenStorage();

        if (null !== $user) {
            $token = new class($user) extends \Symfony\Component\Security\Core\Authentication\Token\AbstractToken {
                public function __construct(private User $user)
                {
                    parent::__construct([]);
                }

                public function getUser(): User
                {
                    return $this->user;
                }

                public function getCredentials(): mixed
                {
                    return null;
                }
            };
            $tokenStorage->setToken($token);
        } else {
            $tokenStorage->setToken(new NullToken());
        }

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            static fn ($data) => json_encode($data)
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn (string $id) => \in_array($id, ['security.token_storage', 'serializer'])
        );
        $container->method('get')->willReturnCallback(static function (string $id) use ($tokenStorage, $serializer) {
            if ('security.token_storage' === $id) {
                return $tokenStorage;
            }
            if ('serializer' === $id) {
                return $serializer;
            }
            throw new \RuntimeException("Service '$id' not found in test container.");
        });

        $ref = new \ReflectionProperty(\Symfony\Bundle\FrameworkBundle\Controller\AbstractController::class, 'container');
        $ref->setAccessible(true);
        $ref->setValue($controller, $container);
    }
}
