<?php

namespace App\Tests\Unit\Service;

use App\DTO\DevTokenRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\DevAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DevAuthServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private UserRepository&MockObject $userRepo;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private DevAuthService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);

        $this->service = new DevAuthService($this->em, $this->userRepo, $this->jwtManager);
    }

    // ── generateDevToken ────────────────────────────────────────────────────

    public function testGenerateDevTokenReturnsTokenForExistingUser(): void
    {
        $user = $this->makeUser(1, 'dev@example.com');
        $dto = $this->makeDto('dev@example.com');

        $this->userRepo->method('findOneBy')->with(['email' => 'dev@example.com'])->willReturn($user);
        $this->jwtManager->method('create')->with($user)->willReturn('jwt-token-abc');

        $this->em->expects($this->never())->method('persist');

        $result = $this->service->generateDevToken($dto);

        $this->assertSame('jwt-token-abc', $result['access_token']);
        $this->assertSame('bearer', $result['token_type']);
        $this->assertSame(1, $result['user']['id']);
        $this->assertSame('dev@example.com', $result['user']['email']);
    }

    public function testGenerateDevTokenCreatesUserWhenNotFound(): void
    {
        $dto = $this->makeDto('new@example.com', 'New Dev');

        $this->userRepo->method('findOneBy')->willReturn(null);
        $this->jwtManager->method('create')->willReturn('new-jwt-token');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->generateDevToken($dto);

        $this->assertSame('new-jwt-token', $result['access_token']);
        $this->assertSame('new@example.com', $result['user']['email']);
        $this->assertSame('New Dev', $result['user']['name']);
    }

    public function testGenerateDevTokenUsesDefaultNameWhenNotProvided(): void
    {
        $dto = $this->makeDto('anon@example.com');
        $dto->name = null;

        $this->userRepo->method('findOneBy')->willReturn(null);
        $this->jwtManager->method('create')->willReturn('token');

        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->generateDevToken($dto);

        $this->assertSame('Dev User', $result['user']['name']);
    }

    public function testGenerateDevTokenResponseContainsExpectedKeys(): void
    {
        $user = $this->makeUser();
        $dto = $this->makeDto('test@example.com');

        $this->userRepo->method('findOneBy')->willReturn($user);
        $this->jwtManager->method('create')->willReturn('token');

        $result = $this->service->generateDevToken($dto);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('id', $result['user']);
        $this->assertArrayHasKey('email', $result['user']);
        $this->assertArrayHasKey('name', $result['user']);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

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

    private function makeDto(string $email, ?string $name = 'Dev User'): DevTokenRequest
    {
        $dto = new DevTokenRequest();
        $dto->email = $email;
        $dto->name = $name;
        return $dto;
    }
}
