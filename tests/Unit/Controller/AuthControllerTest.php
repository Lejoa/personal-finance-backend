<?php

namespace App\Tests\Unit\Controller;

use App\Controller\AuthController;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * AuthController is declared final, so getMockBuilder can't be used.
 * We inject a minimal container that provides the token_storage service
 * so that getUser() and json() work without a full kernel boot.
 */
class AuthControllerTest extends TestCase
{
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->controller = new AuthController();
        $this->injectContainer($this->controller, null);
    }

    private function injectContainer(AuthController $controller, ?User $user): void
    {
        $tokenStorage = new TokenStorage();
        if ($user !== null) {
            $token = new class($user) extends \Symfony\Component\Security\Core\Authentication\Token\AbstractToken {
                public function __construct(private User $user) { parent::__construct([]); }
                public function getUser(): User { return $this->user; }
                public function getCredentials(): mixed { return null; }
            };
            $tokenStorage->setToken($token);
        } else {
            $tokenStorage->setToken(new NullToken());
        }

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(
            fn ($data) => json_encode($data)
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            fn (string $id) => in_array($id, ['security.token_storage', 'serializer'])
        );
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $serializer) {
            if ($id === 'security.token_storage') return $tokenStorage;
            if ($id === 'serializer') return $serializer;
            throw new \RuntimeException("Service '$id' not found in test container.");
        });

        $ref = new \ReflectionProperty(\Symfony\Bundle\FrameworkBundle\Controller\AbstractController::class, 'container');
        $ref->setAccessible(true);
        $ref->setValue($controller, $container);
    }

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

    private function makeRefreshToken(User $user): RefreshToken
    {
        $token = new RefreshToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(16)));
        $token->setExpiresAt(new \DateTime('+30 days'));
        return $token;
    }

    public function testRefreshTokenReturns400WhenBodyMissingRefreshToken(): void
    {
        /** @var RefreshTokenRepository&MockObject $tokenRepo */
        $tokenRepo = $this->createMock(RefreshTokenRepository::class);
        /** @var JWTTokenManagerInterface&MockObject $jwtManager */
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $this->controller->refreshToken(
            $this->makeRequest([]),
            $tokenRepo,
            $jwtManager,
            $em
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Missing refresh token', $data['error']);
    }

    public function testRefreshTokenReturns401WhenTokenInvalid(): void
    {
        /** @var RefreshTokenRepository&MockObject $tokenRepo */
        $tokenRepo = $this->createMock(RefreshTokenRepository::class);
        $tokenRepo->method('findValidToken')->willReturn(null);
        /** @var JWTTokenManagerInterface&MockObject $jwtManager */
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $this->controller->refreshToken(
            $this->makeRequest(['refreshToken' => 'invalid-token']),
            $tokenRepo,
            $jwtManager,
            $em
        );

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid refresh token', $data['error']);
    }

    public function testRefreshTokenReturns200AndRotatesToken(): void
    {
        $user = $this->makeUser();
        $oldToken = $this->makeRefreshToken($user);

        /** @var RefreshTokenRepository&MockObject $tokenRepo */
        $tokenRepo = $this->createMock(RefreshTokenRepository::class);
        $tokenRepo->method('findValidToken')->willReturn($oldToken);
        /** @var JWTTokenManagerInterface&MockObject $jwtManager */
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->method('create')->willReturn('new-access-token');
        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $this->controller->refreshToken(
            $this->makeRequest(['refreshToken' => 'valid-token']),
            $tokenRepo,
            $jwtManager,
            $em
        );

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('accessToken', $data);
        $this->assertArrayHasKey('refreshToken', $data);
        $this->assertTrue($oldToken->isRevoked());
    }

    public function testLogoutReturns200EvenWithoutRefreshToken(): void
    {
        /** @var RefreshTokenRepository&MockObject $tokenRepo */
        $tokenRepo = $this->createMock(RefreshTokenRepository::class);
        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $response = $this->controller->logout(
            $this->makeRequest([]),
            $tokenRepo,
            $em
        );

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Successfully logged out', $data['message']);
    }

    public function testLogoutRevokesTokenWhenProvided(): void
    {
        $user = $this->makeUser();
        $this->injectContainer($this->controller, $user);
        $token = $this->makeRefreshToken($user);

        /** @var RefreshTokenRepository&MockObject $tokenRepo */
        $tokenRepo = $this->createMock(RefreshTokenRepository::class);
        $tokenRepo->method('findValidToken')->willReturn($token);
        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        $this->controller->logout(
            $this->makeRequest(['refreshToken' => 'some-token']),
            $tokenRepo,
            $em
        );

        $this->assertTrue($token->isRevoked());
    }
}
