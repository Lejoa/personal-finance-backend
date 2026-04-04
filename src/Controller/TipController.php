<?php

namespace App\Controller;

use App\DTO\CreateTipRequest;
use App\DTO\UpdateTipRequest;
use App\Service\TipService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tips')]
#[IsGranted('ROLE_USER')]
class TipController extends AbstractController
{
    public function __construct(
        private readonly TipService $tipService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Create a new tip
     */
    #[Route('', name: 'api_tip_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                CreateTipRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $tip = $this->tipService->createTip($dto);

            return $this->json(
                [
                    'message' => 'Tip creado exitosamente',
                    'tip' => $this->serializeTip($tip)
                ],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al crear tip', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get all tips
     */
    #[Route('', name: 'api_tip_list', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $tips = $this->tipService->getAllTips();

        return $this->json([
            'data' => array_map(
                fn($tip) => $this->serializeTip($tip),
                $tips
            ),
            'total' => count($tips)
        ]);
    }

    /**
     * Get recommended tips for the authenticated user, each with a contextual reason
     */
    #[Route('/recommended', name: 'api_tip_recommended', methods: ['GET'])]
    public function recommended(): JsonResponse
    {
        $results = $this->tipService->getRecommendedTips($this->getUser());

        return $this->json([
            'data' => array_map(
                fn($result) => array_merge(
                    $this->serializeTip($result['tip']),
                    ['reason' => $result['reason']]
                ),
                $results
            ),
            'total' => count($results)
        ]);
    }

    /**
     * Get a specific tip by ID
     */
    #[Route('/{id}', name: 'api_tip_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $tip = $this->tipService->getTipById($id);

        if (!$tip) {
            return $this->json(
                ['error' => 'Tip no encontrado'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
            'tip' => $this->serializeTip($tip)
        ]);
    }

    /**
     * Update an existing tip
     */
    #[Route('/{id}', name: 'api_tip_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tip = $this->tipService->getTipById($id);

        if (!$tip) {
            return $this->json(
                ['error' => 'Tip no encontrado'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                UpdateTipRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $updatedTip = $this->tipService->updateTip($tip, $dto);

            return $this->json([
                'message' => 'Tip actualizado exitosamente',
                'tip' => $this->serializeTip($updatedTip)
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al actualizar tip', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete a tip
     */
    #[Route('/{id}', name: 'api_tip_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $tip = $this->tipService->getTipById($id);

        if (!$tip) {
            return $this->json(
                ['error' => 'Tip no encontrado'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->tipService->deleteTip($tip);

            return $this->json([
                'message' => 'Tip eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al eliminar tip', 'message' => $e->getMessage()],
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
     * Serialize tip to array
     */
    private function serializeTip($tip): array
    {
        return [
            'id'               => $tip->getId(),
            'title'            => $tip->getTitle(),
            'author'           => $tip->getAuthor(),
            'authorTitle'      => $tip->getAuthorTitle(),
            'shortDescription' => $tip->getShortDescription(),
            'description'      => $tip->getDescription(),
            'imageSrc'         => $tip->getImageSrc(),
            'tags'             => $tip->getTags(),
            'createdAt'        => $tip->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}