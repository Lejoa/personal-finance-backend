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
     * Create a new category
     */
    public function createCategory(CreateCategoryRequest $dto): Category
    {
        $category = new Category();
        $category->setName($dto->name);
        $category->setDescription($dto->description);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * Update an existing category
     */
    public function updateCategory(Category $category, UpdateCategoryRequest $dto): Category
    {
        if ($dto->name !== null) {
            $category->setName($dto->name);
        }

        if ($dto->description !== null) {
            $category->setDescription($dto->description);
        }

        $this->entityManager->flush();

        return $category;
    }

    /**
     * Delete a category
     */
    public function deleteCategory(Category $category): void
    {
        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    /**
     * Get all categories with optional filters
     */
    public function getAllCategories($user = null, ?string $type = null, ?string $name = null): array
    {
        $criteria = [];

        if ($type !== null) {
            $criteria['type'] = $type;
        }

        if ($name !== null) {
            $criteria['name'] = $name;
        }

        return $this->categoryRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Get category by ID
     */
    public function getCategoryById(int $id): ?Category
    {
        return $this->categoryRepository->find($id);
    }
}