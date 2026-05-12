<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\TransactionRepository;
use App\Service\FinancialDigestService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class FinancialDigestServiceTest extends TestCase
{
    private TransactionRepository&MockObject $transactionRepo;
    private BudgetRepository&MockObject $budgetRepo;
    private CacheInterface&MockObject $cache;
    private FinancialDigestService $service;

    protected function setUp(): void
    {
        $this->transactionRepo = $this->createMock(TransactionRepository::class);
        $this->budgetRepo = $this->createMock(BudgetRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->service = new FinancialDigestService(
            $this->transactionRepo,
            $this->budgetRepo,
            $this->cache
        );
    }

    // ── getDigest ───────────────────────────────────────────────────────────

    public function testGetDigestDelegatesToCache(): void
    {
        $user = $this->makeUser(1);
        $expectedDigest = ['spending_velocity' => 50000.0];

        $this->cache->method('get')
            ->willReturnCallback(static function (string $key, callable $callback) use ($expectedDigest) {
                return $expectedDigest;
            });

        $result = $this->service->getDigest($user);

        $this->assertSame($expectedDigest, $result);
    }

    public function testGetDigestUsesCacheKeyWithUserId(): void
    {
        $user = $this->makeUser(42);

        $this->cache->expects($this->once())
            ->method('get')
            ->with('financial_digest_42', $this->anything())
            ->willReturn([]);

        $this->service->getDigest($user);
    }

    // ── invalidate ──────────────────────────────────────────────────────────

    public function testInvalidateDeletesCacheKey(): void
    {
        $user = $this->makeUser(7);

        $this->cache->expects($this->once())
            ->method('delete')
            ->with('financial_digest_7');

        $this->service->invalidate($user);
    }

    // ── computeFinancialLevel ───────────────────────────────────────────────

    public function testComputeFinancialLevelReturnsPrincipianteForNewUser(): void
    {
        $user = $this->makeUser(1, new \DateTimeImmutable('now'));

        $digest = [
            'consistent_months' => 0,
            'budget_health' => [],
            'savings_improving' => false,
            'category_trends' => [],
        ];

        $level = $this->service->computeFinancialLevel($user, $digest);

        $this->assertSame('principiante', $level);
    }

    public function testComputeFinancialLevelReturnsAvanzadoForHighScore(): void
    {
        $user = $this->makeUser(1, new \DateTimeImmutable('-120 days'));

        $budgetHealthItems = array_fill(0, 3, ['pct_used' => 80.0]);
        $categoryTrends = array_fill(0, 5, ['name' => 'Cat']);

        $digest = [
            'consistent_months' => 3,
            'budget_health' => $budgetHealthItems,
            'savings_improving' => true,
            'category_trends' => $categoryTrends,
        ];

        $level = $this->service->computeFinancialLevel($user, $digest);

        $this->assertSame('avanzado', $level);
    }

    public function testComputeFinancialLevelReturnsIntermedioForMidScore(): void
    {
        $user = $this->makeUser(1, new \DateTimeImmutable('-60 days'));

        $digest = [
            'consistent_months' => 2,
            'budget_health' => [['pct_used' => 50.0]],
            'savings_improving' => false,
            'category_trends' => array_fill(0, 3, ['name' => 'Cat']),
        ];

        $level = $this->service->computeFinancialLevel($user, $digest);

        $this->assertSame('intermedio', $level);
    }

    public function testComputeFinancialLevelConsidersAccountAge(): void
    {
        // An old account with high engagement should reach a higher level
        $userOld = $this->makeUser(1, new \DateTimeImmutable('-100 days'));

        $digest = [
            'consistent_months' => 3,
            'budget_health' => [['pct_used' => 80.0]],
            'savings_improving' => true,
            'category_trends' => array_fill(0, 5, ['name' => 'Cat']),
        ];

        $level = $this->service->computeFinancialLevel($userOld, $digest);

        $this->assertSame('avanzado', $level);
    }

    public function testComputeFinancialLevelAllUnderBudgetAddsBonus(): void
    {
        $user = $this->makeUser(1, new \DateTimeImmutable('-120 days'));

        $digest = [
            'consistent_months' => 3,
            'budget_health' => [
                ['pct_used' => 80.0],
                ['pct_used' => 90.0],
            ],
            'savings_improving' => true,
            'category_trends' => array_fill(0, 5, ['name' => 'Cat']),
        ];

        $level = $this->service->computeFinancialLevel($user, $digest);

        $this->assertSame('avanzado', $level);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function makeUser(int $id = 1, ?\DateTimeImmutable $createdAt = null): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test');
        if (null !== $createdAt) {
            $ref = new \ReflectionProperty(User::class, 'createdAt');
            $ref->setAccessible(true);
            $ref->setValue($user, $createdAt);
        }
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }
}
