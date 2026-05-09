<?php

namespace App\Tests\Unit\Service;

use App\DTO\CreateLoanCalculationRequest;
use App\Entity\LoanCalculation;
use App\Entity\User;
use App\Repository\LoanCalculationRepository;
use App\Service\LoanCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LoanCalculationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private LoanCalculationRepository&MockObject $repo;
    private LoanCalculationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(LoanCalculationRepository::class);

        $this->service = new LoanCalculationService($this->em, $this->repo);
    }

    // ── getByUser ───────────────────────────────────────────────────────────

    public function testGetByUserDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $calc = $this->makeCalc($user);
        $this->repo->method('findByUser')->with($user)->willReturn([$calc]);

        $result = $this->service->getByUser($user);

        $this->assertCount(1, $result);
        $this->assertSame($calc, $result[0]);
    }

    public function testGetByUserReturnsEmptyArrayWhenNone(): void
    {
        $user = $this->makeUser();
        $this->repo->method('findByUser')->willReturn([]);

        $this->assertSame([], $this->service->getByUser($user));
    }

    // ── create ──────────────────────────────────────────────────────────────

    public function testCreatePersistsAndReturnsLoanCalculation(): void
    {
        $user = $this->makeUser();
        $dto = $this->makeDto();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->create($user, $dto);

        $this->assertInstanceOf(LoanCalculation::class, $result);
        $this->assertSame($user, $result->getUser());
        $this->assertSame('Casa propia', $result->getName());
        $this->assertSame(100_000_000.0, $result->getAmount());
        $this->assertSame(12.5, $result->getAnnualRate());
        $this->assertSame(120, $result->getTermMonths());
        $this->assertSame(0.0, $result->getExtraPayment());
        $this->assertSame('monthly', $result->getFrequency());
        $this->assertSame(1_500_000.0, $result->getPeriodicPayment());
        $this->assertSame(80_000_000.0, $result->getTotalInterest());
        $this->assertSame(180_000_000.0, $result->getTotalPaid());
    }

    // ── delete ──────────────────────────────────────────────────────────────

    public function testDeleteRemovesWhenOwner(): void
    {
        $user = $this->makeUser(1);
        $calc = $this->makeCalc($user, 10);
        $this->repo->method('find')->with(10)->willReturn($calc);

        $this->em->expects($this->once())->method('remove')->with($calc);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete(10, $user);
    }

    public function testDeleteThrowsNotFoundWhenMissing(): void
    {
        $user = $this->makeUser();
        $this->repo->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->service->delete(999, $user);
    }

    public function testDeleteThrowsAccessDeniedWhenDifferentOwner(): void
    {
        $owner = $this->makeUser(1);
        $other = $this->makeUser(2);
        $calc = $this->makeCalc($owner, 5);
        $this->repo->method('find')->with(5)->willReturn($calc);

        $this->expectException(AccessDeniedHttpException::class);

        $this->service->delete(5, $other);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function makeUser(int $id = 1): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);
        return $user;
    }

    private function makeCalc(User $user, int $id = 1): LoanCalculation
    {
        $calc = new LoanCalculation();
        $calc->setUser($user);
        $calc->setName('Test Loan');
        $calc->setAmount(50_000_000.0);
        $calc->setAnnualRate(10.0);
        $calc->setTermMonths(60);
        $calc->setExtraPayment(0.0);
        $calc->setFrequency('monthly');
        $calc->setPeriodicPayment(1_000_000.0);
        $calc->setTotalInterest(10_000_000.0);
        $calc->setTotalPaid(60_000_000.0);
        $ref = new \ReflectionProperty(LoanCalculation::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($calc, $id);
        return $calc;
    }

    private function makeDto(): CreateLoanCalculationRequest
    {
        $dto = new CreateLoanCalculationRequest();
        $dto->name = 'Casa propia';
        $dto->amount = 100_000_000.0;
        $dto->annualRate = 12.5;
        $dto->termMonths = 120;
        $dto->extraPayment = 0.0;
        $dto->frequency = 'monthly';
        $dto->periodicPayment = 1_500_000.0;
        $dto->totalInterest = 80_000_000.0;
        $dto->totalPaid = 180_000_000.0;
        return $dto;
    }
}
