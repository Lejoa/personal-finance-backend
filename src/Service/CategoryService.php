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
        $category->setNombre($dto->nombre);
        $category->setDescripcion($dto->descripcion);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    /**
     * Update an existing category
     */
    public function updateCategory(Category $category, UpdateCategoryRequest $dto): Category
    {
        if ($dto->nombre !== null) {
            $category->setNombre($dto->nombre);
        }

        if ($dto->descripcion !== null) {
            $category->setDescripcion($dto->descripcion);
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
     * Get all categories
     */
    public function getAllCategories(): array
    {
        return $this->categoryRepository->findBy(
            [],
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