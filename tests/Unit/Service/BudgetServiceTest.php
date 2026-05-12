<?php

namespace App\Tests\Unit\Service;

use App\DTO\CreateBudgetRequest;
use App\DTO\UpdateBudgetRequest;
use App\Entity\Budget;
use App\Entity\BudgetCategory;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\BudgetCategoryRepository;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Service\BudgetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BudgetServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private BudgetRepository&MockObject $budgetRepo;
    private BudgetCategoryRepository&MockObject $budgetCategoryRepo;
    private CategoryRepository&MockObject $categoryRepo;
    private BudgetService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->budgetRepo = $this->createMock(BudgetRepository::class);
        $this->budgetCategoryRepo = $this->createMock(BudgetCategoryRepository::class);
        $this->categoryRepo = $this->createMock(CategoryRepository::class);

        $this->service = new BudgetService(
            $this->em,
            $this->budgetRepo,
            $this->budgetCategoryRepo,
            $this->categoryRepo
        );
    }

    // ── createBudget ────────────────────────────────────────────────────────

    public function testCreateBudgetPersistsAndReturnsBudget(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory(1);

        $dto = new CreateBudgetRequest();
        $dto->startDate = '2025-01-01';
        $dto->endDate = '2025-01-31';
        $dto->categories = [['categoryId' => 1, 'amount' => 500000]];

        $this->categoryRepo->method('find')->with(1)->willReturn($category);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createBudget($dto, $user);

        $this->assertInstanceOf(Budget::class, $result);
        $this->assertSame($user, $result->getUser());
        $this->assertCount(1, $result->getBudgetCategories());
    }

    public function testCreateBudgetThrowsWhenCategoryNotFound(): void
    {
        $user = $this->makeUser();

        $dto = new CreateBudgetRequest();
        $dto->startDate = '2025-01-01';
        $dto->endDate = '2025-01-31';
        $dto->categories = [['categoryId' => 999, 'amount' => 100]];

        $this->categoryRepo->method('find')->with(999)->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('999');

        $this->service->createBudget($dto, $user);
    }

    public function testCreateBudgetWithNoCategoriesPersistsBudget(): void
    {
        $user = $this->makeUser();
        $dto = new CreateBudgetRequest();
        $dto->startDate = '2025-01-01';
        $dto->endDate = '2025-01-31';
        $dto->categories = [];

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createBudget($dto, $user);

        $this->assertInstanceOf(Budget::class, $result);
        $this->assertCount(0, $result->getBudgetCategories());
    }

    // ── updateBudget ────────────────────────────────────────────────────────

    public function testUpdateBudgetUpdatesStartDate(): void
    {
        $budget = $this->makeBudget();
        $dto = new UpdateBudgetRequest();
        $dto->startDate = '2025-06-01';

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateBudget($budget, $dto);

        $this->assertSame('2025-06-01', $result->getStartDate()->format('Y-m-d'));
    }

    public function testUpdateBudgetThrowsOnInvalidStartDate(): void
    {
        $budget = $this->makeBudget();
        $dto = new UpdateBudgetRequest();
        $dto->startDate = 'not-a-date';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid start date format');

        $this->service->updateBudget($budget, $dto);
    }

    public function testUpdateBudgetThrowsOnInvalidEndDate(): void
    {
        $budget = $this->makeBudget();
        $dto = new UpdateBudgetRequest();
        $dto->endDate = 'bad-date';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid end date format');

        $this->service->updateBudget($budget, $dto);
    }

    public function testUpdateBudgetReplacesCategoriesWhenProvided(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory(2);
        $budget = $this->makeBudget($user);

        $dto = new UpdateBudgetRequest();
        $dto->categories = [['categoryId' => 2, 'amount' => 800000]];

        $this->categoryRepo->method('find')->with(2)->willReturn($category);
        $this->em->method('remove');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateBudget($budget, $dto);

        $this->assertCount(1, $result->getBudgetCategories());
    }

    public function testUpdateBudgetThrowsWhenReplacementCategoryNotFound(): void
    {
        $budget = $this->makeBudget();
        $dto = new UpdateBudgetRequest();
        $dto->categories = [['categoryId' => 999, 'amount' => 100]];

        $this->categoryRepo->method('find')->with(999)->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->updateBudget($budget, $dto);
    }

    // ── deleteBudget ────────────────────────────────────────────────────────

    public function testDeleteBudgetRemovesAndFlushes(): void
    {
        $budget = $this->makeBudget();

        $this->em->expects($this->once())->method('remove')->with($budget);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteBudget($budget);
    }

    // ── updateBudgetCategoryAmount ──────────────────────────────────────────

    public function testUpdateBudgetCategoryAmountSetsAmountAndFlushes(): void
    {
        $bc = $this->makeBudgetCategory();

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateBudgetCategoryAmount($bc, 750000.0);

        $this->assertSame(750000.0, $result->getAmount());
    }

    // ── removeBudgetCategory ───────────────────────────────────────────────

    public function testRemoveBudgetCategoryRemovesEntry(): void
    {
        $budget = $this->makeBudget();
        $category = $this->makeCategory();
        $bc = new BudgetCategory();
        $bc->setBudget($budget);
        $bc->setCategory($category);
        $bc->setAmount(100.0);
        $budget->addBudgetCategory($bc);

        $this->em->expects($this->atLeastOnce())->method('remove');
        $this->em->expects($this->once())->method('flush');

        $this->service->removeBudgetCategory($bc);
    }

    public function testRemoveBudgetCategoryAlsoRemovesBudgetWhenEmpty(): void
    {
        $budget = $this->makeBudget();
        $category = $this->makeCategory();
        $bc = new BudgetCategory();
        $bc->setBudget($budget);
        $bc->setCategory($category);
        $bc->setAmount(100.0);
        $budget->addBudgetCategory($bc);

        $removedEntities = [];
        $this->em->method('remove')->willReturnCallback(static function ($entity) use (&$removedEntities) {
            $removedEntities[] = $entity::class;
        });

        $this->service->removeBudgetCategory($bc);

        $this->assertContains(Budget::class, $removedEntities);
    }

    // ── getUserBudgets ──────────────────────────────────────────────────────

    public function testGetUserBudgetsDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $this->budgetRepo->method('findBy')->willReturn([$this->makeBudget($user)]);

        $result = $this->service->getUserBudgets($user);

        $this->assertCount(1, $result);
    }

    // ── getUserBudgetById ───────────────────────────────────────────────────

    public function testGetUserBudgetByIdDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $budget = $this->makeBudget($user);
        $this->budgetRepo->method('findByIdForUser')->with(1, $user)->willReturn($budget);

        $result = $this->service->getUserBudgetById(1, $user);

        $this->assertSame($budget, $result);
    }

    public function testGetUserBudgetByIdReturnsNullWhenNotFound(): void
    {
        $user = $this->makeUser();
        $this->budgetRepo->method('findByIdForUser')->willReturn(null);

        $this->assertNull($this->service->getUserBudgetById(99, $user));
    }

    // ── findCategoryInBudget ────────────────────────────────────────────────

    public function testFindCategoryInBudgetDelegatesToRepository(): void
    {
        $bc = $this->makeBudgetCategory();
        $this->budgetCategoryRepo->method('findByIdForBudget')->with(1, 2)->willReturn($bc);

        $result = $this->service->findCategoryInBudget(1, 2);

        $this->assertSame($bc, $result);
    }

    // ── buildFormativeFeedback ──────────────────────────────────────────────

    public function testBuildFeedbackReturnsFirstTimeMessageWhenNoPreviousBudgets(): void
    {
        $category = $this->makeCategory(1, 'Comida');
        $bc = $this->makeBudgetCategory(null, $category, 300000.0);

        $this->budgetCategoryRepo->method('getPreviousBudgetAmounts')->willReturn([]);

        $feedback = $this->service->buildFormativeFeedback($bc);

        $this->assertStringContainsString('Comida', $feedback);
    }

    public function testBuildFeedbackReturnsBelowAverageMessageWhenLimitLower(): void
    {
        $category = $this->makeCategory(1, 'Comida');
        $bc = $this->makeBudgetCategory(null, $category, 100000.0);

        $this->budgetCategoryRepo->method('getPreviousBudgetAmounts')->willReturn([300000.0, 300000.0]);

        $feedback = $this->service->buildFormativeFeedback($bc);

        $this->assertNotNull($feedback);
        $this->assertStringContainsString('Comida', $feedback);
    }

    public function testBuildFeedbackReturnsAboveAverageMessageWhenLimitHigher(): void
    {
        $category = $this->makeCategory(1, 'Comida');
        $bc = $this->makeBudgetCategory(null, $category, 500000.0);

        $this->budgetCategoryRepo->method('getPreviousBudgetAmounts')->willReturn([200000.0, 200000.0]);

        $feedback = $this->service->buildFormativeFeedback($bc);

        $this->assertNotNull($feedback);
        $this->assertStringContainsString('Comida', $feedback);
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
        $c = new Category();
        $c->setName($name);
        $c->setType('gasto');
        $ref = new \ReflectionProperty(Category::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($c, $id);

        return $c;
    }

    private function makeBudget(?User $user = null, int $id = 1): Budget
    {
        $b = new Budget();
        $b->setUser($user ?? $this->makeUser());
        $ref = new \ReflectionProperty(Budget::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($b, $id);

        return $b;
    }

    private function makeBudgetCategory(?Budget $budget = null, ?Category $category = null, float $amount = 500000.0): BudgetCategory
    {
        $bc = new BudgetCategory();
        $bc->setBudget($budget ?? $this->makeBudget());
        $bc->setCategory($category ?? $this->makeCategory());
        $bc->setAmount($amount);

        return $bc;
    }
}
