<?php

namespace App\Tests\Unit\Service;

use App\DTO\CreateCategoryRequest;
use App\DTO\UpdateCategoryRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CategoryRepository&MockObject $categoryRepo;
    private CategoryService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->categoryRepo = $this->createMock(CategoryRepository::class);

        $this->service = new CategoryService($this->em, $this->categoryRepo);
    }

    // ── createCategory ──────────────────────────────────────────────────────

    public function testCreateCategoryPersistsAndReturnsCategory(): void
    {
        $dto = new CreateCategoryRequest();
        $dto->name = 'Comida';
        $dto->description = 'Gastos de alimentación';
        $dto->type = 'gasto';

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createCategory($dto);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertSame('Comida', $result->getName());
        $this->assertSame('gasto', $result->getType());
        $this->assertSame('Gastos de alimentación', $result->getDescription());
    }

    // ── updateCategory ──────────────────────────────────────────────────────

    public function testUpdateCategoryAppliesNonNullFields(): void
    {
        $category = $this->makeCategory(1, 'Comida');

        $dto = new UpdateCategoryRequest();
        $dto->name = 'Alimentación';
        $dto->description = 'Nueva descripción';

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateCategory($category, $dto);

        $this->assertSame('Alimentación', $result->getName());
        $this->assertSame('Nueva descripción', $result->getDescription());
    }

    public function testUpdateCategorySkipsNullFields(): void
    {
        $category = $this->makeCategory(1, 'Comida');
        $category->setDescription('Original');

        $dto = new UpdateCategoryRequest();
        $dto->name = null;
        $dto->description = null;

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateCategory($category, $dto);

        $this->assertSame('Comida', $result->getName());
        $this->assertSame('Original', $result->getDescription());
    }

    // ── deleteCategory ──────────────────────────────────────────────────────

    public function testDeleteCategoryRemovesWhenNoTransactions(): void
    {
        $category = $this->makeCategory();

        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('count')->with(['category' => $category])->willReturn(0);
        $this->em->method('getRepository')->willReturn($transactionRepo);

        $this->em->expects($this->once())->method('remove')->with($category);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteCategory($category);
    }

    public function testDeleteCategoryThrowsWhenTransactionsExist(): void
    {
        $category = $this->makeCategory(1, 'Comida');

        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('count')->with(['category' => $category])->willReturn(3);
        $this->em->method('getRepository')->willReturn($transactionRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Comida');

        $this->service->deleteCategory($category);
    }

    public function testDeleteCategoryThrowsWithSingularMessageForOneTransaction(): void
    {
        $category = $this->makeCategory(1, 'Comida');

        $transactionRepo = $this->createMock(EntityRepository::class);
        $transactionRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($transactionRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('associated transaction.');

        $this->service->deleteCategory($category);
    }

    // ── getAllCategories ────────────────────────────────────────────────────

    public function testGetAllCategoriesWithNoFilters(): void
    {
        $this->categoryRepo->method('findBy')
            ->with([], ['createdAt' => 'DESC'])
            ->willReturn([$this->makeCategory()]);

        $result = $this->service->getAllCategories();

        $this->assertCount(1, $result);
    }

    public function testGetAllCategoriesFiltersById(): void
    {
        $this->categoryRepo->method('findBy')
            ->with(['type' => 'gasto'], ['createdAt' => 'DESC'])
            ->willReturn([$this->makeCategory()]);

        $result = $this->service->getAllCategories('gasto');

        $this->assertCount(1, $result);
    }

    public function testGetAllCategoriesFiltersByTypeAndName(): void
    {
        $this->categoryRepo->method('findBy')
            ->with(['type' => 'gasto', 'name' => 'Comida'], ['createdAt' => 'DESC'])
            ->willReturn([$this->makeCategory()]);

        $result = $this->service->getAllCategories('gasto', 'Comida');

        $this->assertCount(1, $result);
    }

    // ── getCategoryById ─────────────────────────────────────────────────────

    public function testGetCategoryByIdDelegatesToRepository(): void
    {
        $category = $this->makeCategory(5);
        $this->categoryRepo->method('find')->with(5)->willReturn($category);

        $result = $this->service->getCategoryById(5);

        $this->assertSame($category, $result);
    }

    public function testGetCategoryByIdReturnsNullWhenNotFound(): void
    {
        $this->categoryRepo->method('find')->willReturn(null);

        $this->assertNull($this->service->getCategoryById(99));
    }

    // ── helpers ─────────────────────────────────────────────────────────────

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
}
