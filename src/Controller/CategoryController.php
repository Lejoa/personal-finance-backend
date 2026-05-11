<?php

namespace App\Controller;

use App\DTO\CreateCategoryRequest;
use App\DTO\UpdateCategoryRequest;
use App\Entity\Category;
use App\Service\CategoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/categories')]
#[IsGranted('ROLE_USER')]
class CategoryController extends AbstractController
{
    /**
     * Injects service and infrastructure dependencies via constructor promotion.
     */
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Creates a new category from the request body.
     * Validates the DTO before delegating creation to the service layer.
     * Returns 201 with the serialized category on success.
     */
    #[Route('', name: 'api_category_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize($request->getContent(), CreateCategoryRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $category = $this->categoryService->createCategory($dto);

            return $this->json(
                ['message' => 'Category created successfully', 'category' => $this->serializeCategory($category)],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error creating category', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Returns all categories with optional type and name filters.
     * The response envelope follows the project-standard "data / total" shape.
     */
    #[Route('', name: 'api_category_list', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        $type = $request->query->get('type');
        $name = $request->query->get('name');

        $categories = $this->categoryService->getAllCategories($type, $name);

        return $this->json([
            'data' => array_map(fn (Category $c) => $this->serializeCategory($c), $categories),
            'total' => \count($categories),
        ]);
    }

    /**
     * Returns a single category by its ID.
     * Returns 404 if no category with the given ID exists.
     */
    #[Route('/{id}', name: 'api_category_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        if (null === $category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['category' => $this->serializeCategory($category)]);
    }

    /**
     * Updates an existing category using the request body (supports both PUT and PATCH).
     * Only non-null fields in the DTO are applied. Returns 404 if the category does not exist.
     */
    #[Route('/{id}', name: 'api_category_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        if (null === $category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), UpdateCategoryRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $updatedCategory = $this->categoryService->updateCategory($category, $dto);

            return $this->json([
                'message' => 'Category updated successfully',
                'category' => $this->serializeCategory($updatedCategory),
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error updating category', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Deletes a category by ID.
     * Returns 404 if not found, 409 if the category still has associated transactions.
     */
    #[Route('/{id}', name: 'api_category_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        if (null === $category) {
            return $this->json(['error' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->categoryService->deleteCategory($category);

            return $this->json(['message' => 'Category deleted successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error deleting category', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Converts a Category entity into a plain array for JSON serialization.
     * Uses the null-safe operator on getCreatedAt() because the property type is nullable,
     * even though the constructor always initialises it.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'type' => $category->getType(),
            'createdAt' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Formats a Symfony ConstraintViolationList into a key-value map of field → message.
     * Used to produce structured 400 responses for invalid request bodies.
     */
    private function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }

        return $formatted;
    }
}
