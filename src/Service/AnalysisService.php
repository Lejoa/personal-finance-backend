<?php

namespace App\Service;

use App\Entity\FinancialAnalysis;
use App\Entity\User;
use App\Repository\FinancialAnalysisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AnalysisService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FinancialAnalysisRepository $analysisRepository,
        private readonly FinancialContextService $contextService,
        private readonly NotificationService $notificationService,
        private readonly HttpClientInterface $httpClient,
        private readonly string $llmServiceUrl
    ) {
    }

    /**
     * Generates a pedagogical financial analysis for a user at the given checkpoint ("mid" or "end").
     * Skips generation if an analysis already exists for the current period and checkpoint.
     * Calls the external LLM service, persists the result, and triggers a notification.
     * Returns null if generation was skipped or the LLM returned no content.
     */
    public function generateForUser(User $user, string $checkpoint): ?FinancialAnalysis
    {
        $period = (new \DateTime())->format('Y-m');

        if ($this->analysisRepository->findOneByUserPeriodCheckpoint($user, $period, $checkpoint)) {
            return null;
        }

        $context = $this->contextService->buildContext($user);
        $goal    = $this->buildGoal($checkpoint);

        try {
            $response = $this->httpClient->request('POST', $this->llmServiceUrl . '/llm/financial-insights', [
                'json' => [
                    'user_context' => $context['userContext'],
                    'summary'      => $context['summary'],
                    'categories'   => $context['categories'],
                    'budgets'      => $context['budgets'],
                    'top_tip'      => $context['top_tip'],
                    'goal'         => $goal,
                ],
                'timeout'      => 300,
                'max_duration' => 300,
            ]);

            $data    = $response->toArray();
            $content = $data['insights'][0]['message'] ?? null;
        } catch (\Throwable $e) {
            throw $e;
        }

        if (!$content) {
            return null;
        }

        $analysis = new FinancialAnalysis();
        $analysis->setUser($user);
        $analysis->setPeriod($period);
        $analysis->setCheckpoint($checkpoint);
        $analysis->setContent($content);

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        $this->notificationService->createForAnalysis($user, $analysis);

        return $analysis;
    }

    /**
     * Returns all FinancialAnalysis records for the given user, ordered by generation date descending.
     *
     * @return FinancialAnalysis[]
     */
    public function getUserAnalyses(User $user): array
    {
        return $this->analysisRepository->findByUser($user);
    }

    /**
     * Finds a single FinancialAnalysis by ID, scoped to the authenticated user.
     * Returns null if the analysis does not exist or belongs to a different user.
     */
    public function findByIdAndUser(int $id, User $user): ?FinancialAnalysis
    {
        return $this->analysisRepository->findByIdForUser($id, $user);
    }

    /**
     * Marks a FinancialAnalysis as read and persists the change.
     * Skips the flush if the analysis is already marked as read to avoid unnecessary writes.
     */
    public function markAsRead(FinancialAnalysis $analysis): void
    {
        if (!$analysis->isRead()) {
            $analysis->setIsRead(true);
            $this->entityManager->flush();
        }
    }

    /**
     * Builds the pedagogical goal prompt string for the given checkpoint type.
     * Returns a mid-month prompt for "mid", or an end-of-month prompt otherwise.
     */
    private function buildGoal(string $checkpoint): string
    {
        if ($checkpoint === 'mid') {
            return 'Genera un análisis pedagógico de mediados de mes. Identifica patrones de gasto hasta ahora, señala al menos una fortaleza y una oportunidad de mejora, y termina con una pregunta reflexiva.';
        }

        return 'Genera un análisis pedagógico de cierre de mes. Resume el comportamiento financiero del mes completo, compara con lo esperado en los presupuestos, señala fortalezas y áreas de mejora, y termina con una pregunta reflexiva para el próximo mes.';
    }
}