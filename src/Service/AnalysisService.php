<?php

namespace App\Service;

use App\Entity\FinancialAnalysis;
use App\Entity\User;
use App\Repository\FinancialAnalysisRepository;
use App\Repository\UserRepository;
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
     * Generates a pedagogical analysis for a user at a given checkpoint (mid|end).
     * Skips generation if an analysis already exists for that period+checkpoint.
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
                    'user_context'      => $context['userContext'],
                    'summary'           => $context['summary'],
                    'categories'        => $context['categories'],
                    'budgets'           => $context['budgets'],
                    'top_tip'           => $context['top_tip'],
                    'goal'              => $goal,
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
     * @return FinancialAnalysis[]
     */
    public function getUserAnalyses(User $user): array
    {
        return $this->analysisRepository->findByUser($user);
    }

    public function markAsRead(FinancialAnalysis $analysis): void
    {
        if (!$analysis->isRead()) {
            $analysis->setIsRead(true);
            $this->entityManager->flush();
        }
    }

    private function buildGoal(string $checkpoint): string
    {
        if ($checkpoint === 'mid') {
            return 'Genera un análisis pedagógico de mediados de mes. Identifica patrones de gasto hasta ahora, señala al menos una fortaleza y una oportunidad de mejora, y termina con una pregunta reflexiva.';
        }

        return 'Genera un análisis pedagógico de cierre de mes. Resume el comportamiento financiero del mes completo, compara con lo esperado en los presupuestos, señala fortalezas y áreas de mejora, y termina con una pregunta reflexiva para el próximo mes.';
    }
}