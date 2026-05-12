<?php

namespace App\Tests\Unit\Service;

use App\Constants\TipReasonMessages;
use App\DTO\CreateTipRequest;
use App\DTO\UpdateTipRequest;
use App\Entity\Tip;
use App\Entity\User;
use App\Repository\TipRepository;
use App\Repository\TransactionRepository;
use App\Service\TipService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TipServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TipRepository&MockObject $tipRepo;
    private TransactionRepository&MockObject $transactionRepo;
    private TipService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tipRepo = $this->createMock(TipRepository::class);
        $this->transactionRepo = $this->createMock(TransactionRepository::class);

        $this->service = new TipService($this->em, $this->tipRepo, $this->transactionRepo);
    }

    // ── createTip ──────────────────────────────────────────────────────────

    public function testCreateTipPersistsAndReturnsTip(): void
    {
        $dto = $this->makeCreateDto();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createTip($dto);

        $this->assertInstanceOf(Tip::class, $result);
        $this->assertSame('Ahorra el 20%', $result->getTitle());
        $this->assertSame('Finanzas', $result->getAuthor());
        $this->assertSame('ahorro,finanzas', $result->getTags());
    }

    // ── updateTip ──────────────────────────────────────────────────────────

    public function testUpdateTipAppliesDtoAndFlushes(): void
    {
        $tip = $this->makeTip(1, 'Old title');

        $dto = new UpdateTipRequest();
        $dto->title = 'New title';

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateTip($tip, $dto);

        $this->assertSame('New title', $result->getTitle());
    }

    // ── deleteTip ──────────────────────────────────────────────────────────

    public function testDeleteTipRemovesAndFlushes(): void
    {
        $tip = $this->makeTip();

        $this->em->expects($this->once())->method('remove')->with($tip);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteTip($tip);
    }

    // ── getAllTips ──────────────────────────────────────────────────────────

    public function testGetAllTipsDelegatesToRepository(): void
    {
        $this->tipRepo->method('findBy')
            ->with([], ['createdAt' => 'DESC'])
            ->willReturn([$this->makeTip(1), $this->makeTip(2)]);

        $result = $this->service->getAllTips();

        $this->assertCount(2, $result);
    }

    // ── getTipById ─────────────────────────────────────────────────────────

    public function testGetTipByIdDelegatesToRepository(): void
    {
        $tip = $this->makeTip(5);
        $this->tipRepo->method('find')->with(5)->willReturn($tip);

        $result = $this->service->getTipById(5);

        $this->assertSame($tip, $result);
    }

    public function testGetTipByIdReturnsNullWhenNotFound(): void
    {
        $this->tipRepo->method('find')->willReturn(null);

        $this->assertNull($this->service->getTipById(99));
    }

    // ── getRecommendedTips ─────────────────────────────────────────────────

    public function testGetRecommendedTipsReturnsHighExpensesTagsWhenExpensesExceedIncome(): void
    {
        $user = $this->makeUser();
        $tip = $this->makeTip(1, 'Ahorro tip', 'ahorro');

        $this->transactionRepo->method('getUserFinancialSummary')->willReturn([
            'total_income' => 1_000_000.0,
            'total_expenses' => 1_500_000.0,
            'top_expense_categories' => [],
        ]);

        $this->tipRepo->method('findByTags')
            ->willReturn([$tip]);

        $result = $this->service->getRecommendedTips($user);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('tip', $result[0]);
        $this->assertArrayHasKey('reason', $result[0]);
        $this->assertSame(TipReasonMessages::HIGH_EXPENSES, $result[0]['reason']);
    }

    public function testGetRecommendedTipsReturnsInversionTagWhenSavingsRateHigh(): void
    {
        $user = $this->makeUser();
        $tip = $this->makeTip(1, 'Inversión tip', 'inversión');

        $this->transactionRepo->method('getUserFinancialSummary')->willReturn([
            'total_income' => 2_000_000.0,
            'total_expenses' => 500_000.0,
            'top_expense_categories' => [],
        ]);

        $this->tipRepo->method('findByTags')->willReturn([$tip]);

        $result = $this->service->getRecommendedTips($user);

        $this->assertNotEmpty($result);
        $this->assertSame(TipReasonMessages::HIGH_SAVINGS, $result[0]['reason']);
    }

    public function testGetRecommendedTipsFallsBackToRecentWhenNoTagMatch(): void
    {
        $user = $this->makeUser();
        $tip = $this->makeTip(1, 'Recent tip');

        $this->transactionRepo->method('getUserFinancialSummary')->willReturn([
            'total_income' => 1_000_000.0,
            'total_expenses' => 600_000.0,
            'top_expense_categories' => [],
        ]);

        // No tag match
        $this->tipRepo->method('findByTags')->willReturn([]);
        $this->tipRepo->method('findRecent')->willReturn([$tip]);

        $result = $this->service->getRecommendedTips($user);

        $this->assertNotEmpty($result);
        $this->assertSame(TipReasonMessages::RECENT_FALLBACK, $result[0]['reason']);
    }

    public function testGetRecommendedTipsAttachesTopCategoryReason(): void
    {
        $user = $this->makeUser();
        $tip = $this->makeTip(1, 'Comida tip', 'comida');

        $this->transactionRepo->method('getUserFinancialSummary')->willReturn([
            'total_income' => 1_000_000.0,
            'total_expenses' => 600_000.0,
            'top_expense_categories' => ['comida'],
        ]);

        $this->tipRepo->method('findByTags')->willReturn([$tip]);

        $result = $this->service->getRecommendedTips($user);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Comida', $result[0]['reason']);
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

    private function makeTip(int $id = 1, string $title = 'Test Tip', string $tags = 'ahorro'): Tip
    {
        $tip = new Tip();
        $tip->setTitle($title);
        $tip->setAuthor('Test Author');
        $tip->setDescription('Description');
        $tip->setShortDescription('Short');
        $tip->setTags($tags);
        $ref = new \ReflectionProperty(Tip::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($tip, $id);

        return $tip;
    }

    private function makeCreateDto(): CreateTipRequest
    {
        $dto = new CreateTipRequest();
        $dto->title = 'Ahorra el 20%';
        $dto->author = 'Finanzas';
        $dto->description = 'Descripción detallada';
        $dto->shortDescription = 'Ahorra';
        $dto->authorTitle = 'Experto';
        $dto->imageSrc = 'https://example.com/image.png';
        $dto->tags = 'ahorro,finanzas';

        return $dto;
    }
}
