<?php

namespace App\Tests\Unit\Controller;

use App\Controller\InternalRagController;
use App\Repository\EmbeddingRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;

class InternalRagControllerTest extends AbstractControllerTestCase
{
    private const TOKEN = 'test-secret-token';

    private EmbeddingRepository&MockObject $embeddingRepository;
    private InternalRagController $controller;

    protected function setUp(): void
    {
        $this->embeddingRepository = $this->createMock(EmbeddingRepository::class);
        $this->controller = new InternalRagController($this->embeddingRepository, self::TOKEN);
    }

    // ── Authentication ────────────────────────────────────────────────────────

    public function testSearchReturns401WhenTokenHeaderIsMissing(): void
    {
        $request = $this->makeRequest(['embedding' => [0.1, 0.2]]);

        $response = $this->controller->search($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Unauthorized', json_decode($response->getContent(), true)['error']);
    }

    public function testSearchReturns401WhenTokenIsWrong(): void
    {
        $request = $this->makeRequestWithToken(['embedding' => [0.1, 0.2]], 'wrong-token');

        $response = $this->controller->search($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ── Input validation ─────────────────────────────────────────────────────

    public function testSearchReturns400WhenEmbeddingFieldIsMissing(): void
    {
        $request = $this->makeRequestWithToken(['limit' => 3]);

        $response = $this->controller->search($request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('embedding', json_decode($response->getContent(), true)['error']);
    }

    public function testSearchReturns400WhenEmbeddingIsNotArray(): void
    {
        $request = $this->makeRequestWithToken(['embedding' => 'not-an-array']);

        $response = $this->controller->search($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testSearchReturns400WhenBodyIsEmpty(): void
    {
        $request = new Request([], [], [], [], [], [], '');
        $request->headers->set('X-Internal-Token', self::TOKEN);

        $response = $this->controller->search($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ── Successful search ─────────────────────────────────────────────────────

    public function testSearchReturns200WithResultsFromRepository(): void
    {
        $results = [
            [
                'content'       => 'El ahorro es la base de la riqueza.',
                'tip_title'     => 'Gasta menos de lo que ganas',
                'source_title'  => 'El hombre más rico de Babilonia',
                'source_author' => 'George S. Clason',
                'similarity'    => 0.92,
            ],
        ];
        $this->embeddingRepository->method('findSimilar')->willReturn($results);

        $request = $this->makeRequestWithToken(['embedding' => [0.1, 0.2, 0.3]]);

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('results', $data);
        $this->assertCount(1, $data['results']);
        $this->assertSame('El ahorro es la base de la riqueza.', $data['results'][0]['content']);
    }

    public function testSearchReturns200WithEmptyResultsWhenNothingFound(): void
    {
        $this->embeddingRepository->method('findSimilar')->willReturn([]);

        $request = $this->makeRequestWithToken(['embedding' => [0.5, 0.5]]);

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame([], $data['results']);
    }

    // ── Embedding literal format ──────────────────────────────────────────────

    public function testSearchPassesCorrectEmbeddingLiteralToRepository(): void
    {
        $this->embeddingRepository
            ->expects($this->once())
            ->method('findSimilar')
            ->with('[0.1,0.2,0.3]', $this->anything())
            ->willReturn([]);

        $request = $this->makeRequestWithToken(['embedding' => [0.1, 0.2, 0.3]]);
        $this->controller->search($request);
    }

    public function testSearchPassesEmptyEmbeddingLiteralWhenArrayIsEmpty(): void
    {
        $this->embeddingRepository
            ->expects($this->once())
            ->method('findSimilar')
            ->with('[]', $this->anything())
            ->willReturn([]);

        $request = $this->makeRequestWithToken(['embedding' => []]);
        $this->controller->search($request);
    }

    // ── Limit clamping ────────────────────────────────────────────────────────

    public function testSearchUsesDefaultLimitOfThreeWhenNotProvided(): void
    {
        $this->embeddingRepository
            ->expects($this->once())
            ->method('findSimilar')
            ->with($this->anything(), 3)
            ->willReturn([]);

        $request = $this->makeRequestWithToken(['embedding' => [0.1]]);
        $this->controller->search($request);
    }

    public function testSearchClampsLimitToMinimumOfOne(): void
    {
        $this->embeddingRepository
            ->expects($this->once())
            ->method('findSimilar')
            ->with($this->anything(), 1)
            ->willReturn([]);

        $request = $this->makeRequestWithToken(['embedding' => [0.1], 'limit' => 0]);
        $this->controller->search($request);
    }

    public function testSearchClampsLimitToMaximumOfTen(): void
    {
        $this->embeddingRepository
            ->expects($this->once())
            ->method('findSimilar')
            ->with($this->anything(), 10)
            ->willReturn([]);

        $request = $this->makeRequestWithToken(['embedding' => [0.1], 'limit' => 100]);
        $this->controller->search($request);
    }

    public function testSearchUsesExactLimitWhenWithinBounds(): void
    {
        $this->embeddingRepository
            ->expects($this->once())
            ->method('findSimilar')
            ->with($this->anything(), 5)
            ->willReturn([]);

        $request = $this->makeRequestWithToken(['embedding' => [0.1], 'limit' => 5]);
        $this->controller->search($request);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeRequestWithToken(array $body, string $token = self::TOKEN): Request
    {
        $request = $this->makeRequest($body);
        $request->headers->set('X-Internal-Token', $token);

        return $request;
    }
}
