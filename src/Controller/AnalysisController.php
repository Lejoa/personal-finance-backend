<?php

namespace App\Controller;

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
    public function __construct(
        private readonly AnalysisService $analysisService
    ) {
    }

    /**
     * Get all analyses for the authenticated user
     */
    #[Route('', name: 'api_analysis_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user     = $this->getUser();
        $analyses = $this->analysisService->getUserAnalyses($user);

        return $this->json([
            'data'  => array_map(fn($a) => $this->serializeAnalysis($a), $analyses),
            'total' => count($analyses),
        ]);
    }

    /**
     * Get a specific analysis and mark it as read
     */
    #[Route('/{id}', name: 'api_analysis_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user     = $this->getUser();
        $analyses = $this->analysisService->getUserAnalyses($user);

        $analysis = null;
        foreach ($analyses as $a) {
            if ($a->getId() === $id) {
                $analysis = $a;
                break;
            }
        }

        if (!$analysis) {
            return $this->json(['error' => 'Análisis no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $this->analysisService->markAsRead($analysis);

        return $this->json(['analysis' => $this->serializeAnalysis($analysis)]);
    }

    private function serializeAnalysis($analysis): array
    {
        return [
            'id'          => $analysis->getId(),
            'period'      => $analysis->getPeriod(),
            'checkpoint'  => $analysis->getCheckpoint(),
            'content'     => $analysis->getContent(),
            'isRead'      => $analysis->isRead(),
            'generatedAt' => $analysis->getGeneratedAt()->format('Y-m-d H:i:s'),
        ];
    }
}