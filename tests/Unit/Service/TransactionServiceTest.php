<?php

namespace App\Tests\Unit\Service;

use App\DTO\CreateTransactionRequest;
use App\DTO\UpdateTransactionRequest;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use App\Service\FinancialDigestService;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransactionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TransactionRepository&MockObject $transactionRepo;
    private CategoryRepository&MockObject $categoryRepo;
    private FinancialDigestService&MockObject $digestService;
    private TransactionService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->transactionRepo = $this->createMock(TransactionRepository::class);
        $this->categoryRepo = $this->createMock(CategoryRepository::class);
        $this->digestService = $this->createMock(FinancialDigestService::class);

        $this->service = new TransactionService(
            $this->em,
            $this->transactionRepo,
            $this->categoryRepo,
            $this->digestService
        );
    }

    // ── createTransaction ───────────────────────────────────────────────────

    public function testCreateTransactionPersistsAndReturnsTransaction(): void
    {
        $user = $this->makeUser();
        $dto = new CreateTransactionRequest();
        $dto->name = 'Lunch';
        $dto->type = 'gasto';
        $dto->amount = 15000.0;
        $dto->date = '2025-05-01';

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->digestService->expects($this->once())->method('invalidate');

        $result = $this->service->createTransaction($dto, $user);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertSame('Lunch', $result->getName());
        $this->assertSame('gasto', $result->getType());
        $this->assertSame(15000.0, $result->getAmount());
    }

    public function testCreateTransactionDeduplicatesSmsSource(): void
    {
        $user = $this->makeUser();
        $existing = new Transaction();
        $existing->setName('SMS purchase');

        $dto = new CreateTransactionRequest();
        $dto->source = 'sms';
        $dto->note = 'Ref#12345';
        $dto->name = 'SMS purchase';
        $dto->type = 'gasto';
        $dto->amount = 5000.0;

        $this->transactionRepo->method('findExistingByNote')->willReturn($existing);

        $this->em->expects($this->never())->method('persist');
        $this->digestService->expects($this->never())->method('invalidate');

        $result = $this->service->createTransaction($dto, $user);

        $this->assertSame($existing, $result);
    }

    public function testCreateTransactionWithoutNoteDoesNotDeduplicate(): void
    {
        $user = $this->makeUser();
        $dto = new CreateTransactionRequest();
        $dto->source = 'sms';
        $dto->note = null;
        $dto->name = 'SMS';
        $dto->type = 'gasto';
        $dto->amount = 5000.0;
        $dto->date = '2025-05-01';

        $this->transactionRepo->expects($this->never())->method('findExistingByNote');
        $this->em->expects($this->once())->method('persist');

        $this->service->createTransaction($dto, $user);
    }

    // ── updateTransaction ───────────────────────────────────────────────────

    public function testUpdateTransactionAppliesDto(): void
    {
        $transaction = new Transaction();
        $transaction->setName('Old name');
        $transaction->setType('gasto');
        $transaction->setAmount(1000.0);

        $dto = new UpdateTransactionRequest();
        $dto->name = 'New name';

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateTransaction($transaction, $dto);

        $this->assertSame('New name', $result->getName());
    }

    public function testUpdateTransactionAssignsCategoryWhenIdProvided(): void
    {
        $transaction = new Transaction();
        $transaction->setName('Test');
        $transaction->setType('gasto');
        $transaction->setAmount(1000.0);

        $category = $this->makeCategory(5);
        $dto = new UpdateTransactionRequest();
        $dto->categoryId = 5;

        $this->categoryRepo->method('find')->with(5)->willReturn($category);

        $this->service->updateTransaction($transaction, $dto);

        $this->assertSame($category, $transaction->getCategory());
    }

    public function testUpdateTransactionIgnoresCategoryWhenNotFound(): void
    {
        $transaction = new Transaction();
        $transaction->setName('Test');
        $transaction->setType('gasto');
        $transaction->setAmount(1000.0);

        $dto = new UpdateTransactionRequest();
        $dto->categoryId = 999;

        $this->categoryRepo->method('find')->with(999)->willReturn(null);

        $this->service->updateTransaction($transaction, $dto);

        $this->assertNull($transaction->getCategory());
    }

    // ── deleteTransaction ───────────────────────────────────────────────────

    public function testDeleteTransactionRemovesAndInvalidatesCache(): void
    {
        $user = $this->makeUser();
        $transaction = new Transaction();
        $transaction->setUser($user);

        $this->em->expects($this->once())->method('remove')->with($transaction);
        $this->em->expects($this->once())->method('flush');
        $this->digestService->expects($this->once())->method('invalidate')->with($user);

        $this->service->deleteTransaction($transaction);
    }

    // ── getUserTransactions ─────────────────────────────────────────────────

    public function testGetUserTransactionsDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $this->transactionRepo->method('findByFilters')
            ->with($user, 'gasto', null, null, 10, null)
            ->willReturn([new Transaction()]);

        $result = $this->service->getUserTransactions($user, 'gasto', null, null, 10, null);

        $this->assertCount(1, $result);
    }

    // ── getTransactionById ──────────────────────────────────────────────────

    public function testGetTransactionByIdDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $transaction = new Transaction();
        $this->transactionRepo->method('findByIdForUser')->with(1, $user)->willReturn($transaction);

        $result = $this->service->getTransactionById(1, $user);

        $this->assertSame($transaction, $result);
    }

    public function testGetTransactionByIdReturnsNullWhenNotFound(): void
    {
        $user = $this->makeUser();
        $this->transactionRepo->method('findByIdForUser')->willReturn(null);

        $this->assertNull($this->service->getTransactionById(99, $user));
    }

    // ── assignCategory ──────────────────────────────────────────────────────

    public function testAssignCategorySetsAndFlushes(): void
    {
        $transaction = new Transaction();
        $category = $this->makeCategory(3);

        $this->em->expects($this->once())->method('flush');

        $this->service->assignCategory($transaction, $category);

        $this->assertSame($category, $transaction->getCategory());
    }

    // ── buildFormativeFeedback ──────────────────────────────────────────────

    public function testBuildFeedbackForIncomeReturnsMonthTotal(): void
    {
        $user = $this->makeUser();
        $transaction = new Transaction();
        $transaction->setType('ingreso');
        $transaction->setAmount(500000.0);

        $this->transactionRepo->method('getCurrentMonthIncome')->with($user)->willReturn(800000.0);

        $feedback = $this->service->buildFormativeFeedback($transaction, $user);

        $this->assertStringContainsString('800.000', $feedback);
        $this->assertStringContainsString('500.000', $feedback);
    }

    public function testBuildFeedbackForExpenseWithoutCategoryReturnsRegisteredMessage(): void
    {
        $user = $this->makeUser();
        $transaction = new Transaction();
        $transaction->setType('gasto');
        $transaction->setAmount(50000.0);

        $feedback = $this->service->buildFormativeFeedback($transaction, $user);

        $this->assertStringContainsString('Gasto registrado', $feedback);
    }

    public function testBuildFeedbackForExpenseWithCategoryAndNoHistoryReturnsFirstExpenseMessage(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory(1, 'Food');
        $transaction = new Transaction();
        $transaction->setType('gasto');
        $transaction->setAmount(50000.0);
        $transaction->setCategory($category);

        $this->transactionRepo->method('getMonthlyCategorySpending')->willReturn([]);
        $this->transactionRepo->method('getCurrentMonthCategorySpending')->willReturn(50000.0);

        $feedback = $this->service->buildFormativeFeedback($transaction, $user);

        $this->assertStringContainsString('Food', $feedback);
    }

    public function testBuildFeedbackForExpenseAboveAverageContainsCategory(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory(1, 'Food');

        $transaction = new Transaction();
        $transaction->setType('gasto');
        $transaction->setAmount(200000.0);
        $transaction->setCategory($category);

        // History: 3 months at 100_000 average; current month total: 200_000 → delta > 20% → ABOVE_AVERAGE
        $this->transactionRepo->method('getMonthlyCategorySpending')->willReturn([
            ['total' => 100000.0],
            ['total' => 100000.0],
            ['total' => 100000.0],
        ]);
        $this->transactionRepo->method('getCurrentMonthCategorySpending')->willReturn(200000.0);

        $feedback = $this->service->buildFormativeFeedback($transaction, $user);

        $this->assertNotNull($feedback);
        $this->assertStringContainsString('Food', $feedback);
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

    private function makeCategory(int $id = 1, string $name = 'Food'): Category
    {
        $category = new Category();
        $category->setName($name);
        $category->setType('gasto');
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($category, $id);

        return $category;
    }

    private function makeTransactionWith(string $type, float $amount): Transaction
    {
        $t = new Transaction();
        $t->setType($type);
        $t->setAmount($amount);

        return $t;
    }
}
