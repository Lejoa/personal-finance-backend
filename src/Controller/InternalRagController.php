<?php

namespace App\Controller;

use App\Repository\EmbeddingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/internal')]
class InternalRagController
{
    public function __construct(
        private readonly EmbeddingRepository $embeddingRepository,
        private readonly string $internalToken,
    ) {
    }

    /**
     * Performs a pgvector cosine similarity search over tip_embeddings.
     * Protected by a shared secret header — not exposed to end users.
     *
     * Expected body: { "embedding": [float, ...], "limit": int }
     * Returns:       { "results": [{ "content", "source_title", "source_author", "similarity" }, ...] }
     */
    #[Route('/rag/search', name: 'internal_rag_search', methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        if ($request->headers->get('X-Internal-Token') !== $this->internalToken) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $body = json_decode($request->getContent(), true);

        if (!isset($body['embedding']) || !is_array($body['embedding'])) {
            return new JsonResponse(['error' => 'Missing or invalid "embedding" field'], Response::HTTP_BAD_REQUEST);
        }

        $limit = isset($body['limit']) ? (int) $body['limit'] : 3;
        $limit = max(1, min(10, $limit));

        $embeddingLiteral = '[' . implode(',', array_map('floatval', $body['embedding'])) . ']';

        $results = $this->embeddingRepository->findSimilar($embeddingLiteral, $limit);

        return new JsonResponse(['results' => $results]);
    }
}
