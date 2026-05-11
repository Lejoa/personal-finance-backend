<?php

namespace App\Controller;

use App\DTO\CreateTipRequest;
use App\DTO\UpdateTipRequest;
use App\Entity\Tip;
use App\Entity\User;
use App\Service\TipService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tips')]
#[IsGranted('ROLE_USER')]
class TipController extends AbstractController
{
    /**
     * Injects service and infrastructure dependencies via constructor promotion.
     */
    public function __construct(
        private readonly TipService $tipService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Creates a new tip from the request body.
     * Validates the DTO before delegating creation to the service layer.
     * Returns 201 with the serialized tip on success.
     */
    #[Route('', name: 'api_tip_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize($request->getContent(), CreateTipRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $tip = $this->tipService->createTip($dto);

            return $this->json(
                ['message' => 'Tip created successfully', 'tip' => $this->serializeTip($tip)],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error creating tip', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Returns all tips ordered by creation date descending.
     * The response envelope follows the project-standard "data / total" shape.
     */
    #[Route('', name: 'api_tip_list', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        $tips = $this->tipService->getAllTips();

        return $this->json([
            'data' => array_map(fn (Tip $tip) => $this->serializeTip($tip), $tips),
            'total' => \count($tips),
        ]);
    }

    /**
     * Returns personalized tip recommendations for the authenticated user.
     * Each result includes the tip data and a contextual reason string explaining
     * why that tip was selected based on the user's financial behaviour.
     * The response envelope follows the project-standard "data / total" shape.
     */
    #[Route('/recommended', name: 'api_tip_recommended', methods: ['GET'])]
    public function recommended(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $results = $this->tipService->getRecommendedTips($user);

        return $this->json([
            'data' => array_map(
                fn ($result) => array_merge($this->serializeTip($result['tip']), ['reason' => $result['reason']]),
                $results
            ),
            'total' => \count($results),
        ]);
    }

    /**
     * Returns a single tip by its ID.
     * Returns 404 if no tip with the given ID exists.
     */
    #[Route('/{id}', name: 'api_tip_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $tip = $this->tipService->getTipById($id);

        if (null === $tip) {
            return $this->json(['error' => 'Tip not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['tip' => $this->serializeTip($tip)]);
    }

    /**
     * Updates an existing tip using the request body (supports both PUT and PATCH).
     * Only non-null fields in the DTO are applied. Returns 404 if the tip does not exist.
     */
    #[Route('/{id}', name: 'api_tip_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tip = $this->tipService->getTipById($id);

        if (null === $tip) {
            return $this->json(['error' => 'Tip not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), UpdateTipRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $updatedTip = $this->tipService->updateTip($tip, $dto);

            return $this->json([
                'message' => 'Tip updated successfully',
                'tip' => $this->serializeTip($updatedTip),
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error updating tip', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Deletes a tip by ID.
     * Returns 404 if no tip with the given ID exists.
     */
    #[Route('/{id}', name: 'api_tip_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $tip = $this->tipService->getTipById($id);

        if (null === $tip) {
            return $this->json(['error' => 'Tip not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->tipService->deleteTip($tip);

            return $this->json(['message' => 'Tip deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error deleting tip', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Converts a Tip entity into a plain array for JSON serialization.
     * Uses the null-safe operator on getCreatedAt() because the property type is nullable,
     * even though the constructor always initialises it.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeTip(Tip $tip): array
    {
        return [
            'id' => $tip->getId(),
            'title' => $tip->getTitle(),
            'author' => $tip->getAuthor(),
            'authorTitle' => $tip->getAuthorTitle(),
            'shortDescription' => $tip->getShortDescription(),
            'description' => $tip->getDescription(),
            'imageSrc' => $tip->getImageSrc(),
            'tags' => $tip->getTags(),
            'createdAt' => $tip->getCreatedAt()?->format('Y-m-d H:i:s'),
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
