<?php

namespace App\Service;

use App\DTO\CreateCategoryRequest;
use App\DTO\UpdateCategoryRequest;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    /**
     * Creates a new Category entity from the given DTO and persists it to the database.
     */
    public function createCategory(CreateCategoryRequest $dto): Category
    {
        $category = new Category();
        $category->setName($dto->name);
        $category->setDescription($dto->description);
        $category->setType($dto->type);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * Applies non-null DTO fields to an existing Category and flushes the change.
     * Fields not present in the DTO (null) are left unchanged.
     */
    public function updateCategory(Category $category, UpdateCategoryRequest $dto): Category
    {
        if (null !== $dto->name) {
            $category->setName($dto->name);
        }

        if (null !== $dto->description) {
            $category->setDescription($dto->description);
        }

        $this->entityManager->flush();

        return $category;
    }

    /**
     * Deletes the given Category from the database.
     * Throws InvalidArgumentException if one or more transactions still reference this category.
     */
    public function deleteCategory(Category $category): void
    {
        $transactionCount = $this->entityManager
            ->getRepository(\App\Entity\Transaction::class)
            ->count(['category' => $category]);

        if ($transactionCount > 0) {
            throw new \InvalidArgumentException("Cannot delete category \"{$category->getName()}\" because it has {$transactionCount} " . (1 === $transactionCount ? 'associated transaction.' : 'associated transactions.'));
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    /**
     * Returns all categories matching the given optional filters, ordered by creation date descending.
     *
     * @return Category[]
     */
    public function getAllCategories(?string $type = null, ?string $name = null): array
    {
        $criteria = [];

        if (null !== $type) {
            $criteria['type'] = $type;
        }

        if (null !== $name) {
            $criteria['name'] = $name;
        }

        return $this->categoryRepository->findBy($criteria, ['createdAt' => 'DESC']);
    }

    /**
     * Finds a single Category by its primary key. Returns null if not found.
     */
    public function getCategoryById(int $id): ?Category
    {
        return $this->categoryRepository->find($id);
    }
}
