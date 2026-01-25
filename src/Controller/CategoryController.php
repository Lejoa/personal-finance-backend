<?php

namespace App\Controller;

use App\DTO\CreateCategoryRequest;
use App\DTO\UpdateCategoryRequest;
use App\Service\CategoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/categories')]
#[IsGranted('ROLE_USER')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Create a new category
     */
    #[Route('', name: 'api_category_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                CreateCategoryRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $category = $this->categoryService->createCategory($dto);

            return $this->json(
                [
                    'message' => 'Categoría creada exitosamente',
                    'category' => $this->serializeCategory($category)
                ],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al crear categoría', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get all categories
     */
    #[Route('', name: 'api_category_list', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $type = $request->query->get('type');
        $name = $request->query->get('name');

        $categories = $this->categoryService->getAllCategories($user, $type, $name);

        return $this->json([
            'data' => array_map(
                fn($category) => $this->serializeCategory($category),
                $categories
            ),
            'total' => count($categories)
        ]);
    }

    /**
     * Get a specific category by ID
     */
    #[Route('/{id}', name: 'api_category_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        if (!$category) {
            return $this->json(
                ['error' => 'Categoría no encontrada'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
            'category' => $this->serializeCategory($category)
        ]);
    }

    /**
     * Update an existing category
     */
    #[Route('/{id}', name: 'api_category_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        if (!$category) {
            return $this->json(
                ['error' => 'Categoría no encontrada'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                UpdateCategoryRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $updatedCategory = $this->categoryService->updateCategory($category, $dto);

            return $this->json([
                'message' => 'Categoría actualizada exitosamente',
                'category' => $this->serializeCategory($updatedCategory)
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al actualizar categoría', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete a category
     */
    #[Route('/{id}', name: 'api_category_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        if (!$category) {
            return $this->json(
                ['error' => 'Categoría no encontrada'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->categoryService->deleteCategory($category);

            return $this->json([
                'message' => 'Categoría eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al eliminar categoría', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Format validation errors
     */
    private function formatErrors($errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }
        return $formatted;
    }

    /**
     * Serialize category to array
     */
    private function serializeCategory($category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'type' => $category->getType(),
            'createdAt' => $category->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }
}