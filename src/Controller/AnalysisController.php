<?php

namespace App\Controller;

use App\Entity\FinancialAnalysis;
use App\Entity\User;
use App\Service\AnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/analyses')]
#[IsGranted('ROLE_USER')]
class AnalysisController extends AbstractController
{
    /**
     * Injects the AnalysisService as a read-only dependency via constructor promotion.
     */
    public function __construct(
        private readonly AnalysisService $analysisService
    ) {
    }

    /**
     * Returns all financial analyses for the authenticated user.
     * Response envelope follows the project-standard "data / total" shape.
     * Wrapped in try/catch to return a structured 500 response on unexpected failures.
     */
    #[Route('', name: 'api_analysis_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $analyses = $this->analysisService->getUserAnalyses($user);

            return $this->json([
                'data' => array_map(fn (FinancialAnalysis $a) => $this->serializeAnalysis($a), $analyses),
                'total' => \count($analyses),
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error retrieving analyses', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Returns a single financial analysis by ID for the authenticated user.
     * Uses a direct user-scoped repository lookup — avoids loading the full collection.
     * Returns 404 if the analysis does not exist or belongs to a different user.
     */
    #[Route('/{id}', name: 'api_analysis_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $analysis = $this->analysisService->findByIdAndUser($id, $user);

        if (null === $analysis) {
            return $this->json(
                ['error' => 'Analysis not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json(['analysis' => $this->serializeAnalysis($analysis)]);
    }

    /**
     * Marks a single financial analysis as read for the authenticated user.
     * Returns 404 if the analysis does not exist or belongs to a different user.
     */
    #[Route('/{id}/read', name: 'api_analysis_mark_read', methods: ['PATCH'])]
    public function markRead(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $analysis = $this->analysisService->findByIdAndUser($id, $user);

        if (null === $analysis) {
            return $this->json(
                ['error' => 'Analysis not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->analysisService->markAsRead($analysis);

        return $this->json(['analysis' => $this->serializeAnalysis($analysis)]);
    }

    /**
     * Converts a FinancialAnalysis entity into a plain array for JSON serialization.
     * Uses the null-safe operator on getGeneratedAt() because the property type is nullable,
     * even though the constructor always initialises it.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeAnalysis(FinancialAnalysis $analysis): array
    {
        return [
            'id' => $analysis->getId(),
            'period' => $analysis->getPeriod(),
            'checkpoint' => $analysis->getCheckpoint(),
            'content' => $analysis->getContent(),
            'isRead' => $analysis->isRead(),
            'generatedAt' => $analysis->getGeneratedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
