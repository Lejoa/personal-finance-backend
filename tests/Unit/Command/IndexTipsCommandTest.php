<?php

namespace App\Tests\Unit\Command;

use App\Command\IndexTipsCommand;
use App\Entity\Tip;
use App\Entity\TipEmbedding;
use App\Entity\TipReference;
use App\Repository\EmbeddingRepository;
use App\Repository\TipReferenceRepository;
use App\Repository\TipRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IndexTipsCommandTest extends TestCase
{
    private const LLM_URL = 'http://llm-test';

    private TipRepository&MockObject $tipRepository;
    private TipReferenceRepository&MockObject $referenceRepository;
    private EmbeddingRepository&MockObject $embeddingRepository;
    private HttpClientInterface&MockObject $httpClient;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tipRepository       = $this->createMock(TipRepository::class);
        $this->referenceRepository = $this->createMock(TipReferenceRepository::class);
        $this->embeddingRepository = $this->createMock(EmbeddingRepository::class);
        $this->httpClient          = $this->createMock(HttpClientInterface::class);

        $command = new IndexTipsCommand(
            $this->tipRepository,
            $this->referenceRepository,
            $this->embeddingRepository,
            $this->httpClient,
            self::LLM_URL,
        );

        $this->commandTester = new CommandTester($command);
    }

    // ── No tips ───────────────────────────────────────────────────────────────

    public function testExecuteSucceedsWhenNoTipsExist(): void
    {
        $this->tipRepository->method('findAll')->willReturn([]);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Indexed: 0, Skipped: 0, Errors: 0', $this->commandTester->getDisplay());
    }

    // ── Skipping ──────────────────────────────────────────────────────────────

    public function testExecuteSkipsTipAlreadyIndexedWhenNoForceFlag(): void
    {
        $tip = $this->makeTip();
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([$this->createMock(TipEmbedding::class)]);
        $this->embeddingRepository->expects($this->never())->method('save');

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Indexed: 0, Skipped: 1, Errors: 0', $this->commandTester->getDisplay());
    }

    public function testExecuteSkipsOnlyAlreadyIndexedTips(): void
    {
        $tipWithEmbeddings = $this->makeTip(1, 'Tip indexado');
        $tipNew            = $this->makeTip(2, 'Tip nuevo');

        $this->tipRepository->method('findAll')->willReturn([$tipWithEmbeddings, $tipNew]);
        $this->embeddingRepository->method('findBy')->willReturnCallback(
            fn (array $criteria) => $criteria['tip'] === $tipWithEmbeddings
                ? [$this->createMock(TipEmbedding::class)]
                : []
        );
        $this->referenceRepository->method('findByTip')->willReturn([]);
        $this->mockSuccessfulHttpResponse();

        $this->commandTester->execute([]);

        $this->assertStringContainsString('Indexed: 1, Skipped: 1, Errors: 0', $this->commandTester->getDisplay());
    }

    // ── Force option ──────────────────────────────────────────────────────────

    public function testExecuteDeletesExistingEmbeddingsAndReIndexesWhenForced(): void
    {
        $tip = $this->makeTip();
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([$this->createMock(TipEmbedding::class)]);
        $this->referenceRepository->method('findByTip')->willReturn([]);
        $this->embeddingRepository->expects($this->once())->method('deleteByTip')->with($tip);
        $this->mockSuccessfulHttpResponse();

        $exitCode = $this->commandTester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Indexed: 1, Skipped: 0, Errors: 0', $this->commandTester->getDisplay());
    }

    public function testExecuteDoesNotDeleteWhenNoExistingEmbeddingsEvenWithForce(): void
    {
        $tip = $this->makeTip();
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([]);
        $this->embeddingRepository->expects($this->never())->method('deleteByTip');
        $this->mockSuccessfulHttpResponse();

        $this->commandTester->execute(['--force' => true]);
    }

    // ── Embedding text format ─────────────────────────────────────────────────

    public function testExecuteSendsTitleAndDescriptionConcatenatedToLlm(): void
    {
        $tip = $this->makeTip(1, 'Invierte a largo plazo', 'La paciencia es clave para el inversor.');
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['embedding' => [0.1]]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('POST', self::LLM_URL . '/llm/embed', $this->callback(
                fn (array $opts) => $opts['json']['text'] === 'Invierte a largo plazo. La paciencia es clave para el inversor.'
            ))
            ->willReturn($response);

        $this->commandTester->execute([]);
    }

    public function testExecuteSendsReferenceExcerptToLlm(): void
    {
        $tip       = $this->makeTip();
        $reference = $this->makeReference($tip, 1, 'El ahorro es la base de toda prosperidad.');
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([$reference]);

        $sentTexts = [];
        $response  = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['embedding' => [0.1]]);
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $m, string $url, array $opts) use ($response, &$sentTexts) {
                $sentTexts[] = $opts['json']['text'];
                return $response;
            });

        $this->commandTester->execute([]);

        $this->assertContains('El ahorro es la base de toda prosperidad.', $sentTexts);
    }

    // ── Save calls ────────────────────────────────────────────────────────────

    public function testExecuteSavesTipEmbeddingWithNullReference(): void
    {
        $tip = $this->makeTip();
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['embedding' => [0.5, 0.6]]);
        $this->httpClient->method('request')->willReturn($response);

        $this->embeddingRepository
            ->expects($this->once())
            ->method('save')
            ->with($tip, null, $this->isType('string'), [0.5, 0.6]);

        $this->commandTester->execute([]);
    }

    public function testExecuteSavesOncePerReferenceInAdditionToTipItself(): void
    {
        $tip  = $this->makeTip();
        $ref1 = $this->makeReference($tip, 1, 'Extracto uno');
        $ref2 = $this->makeReference($tip, 2, 'Extracto dos');
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([$ref1, $ref2]);
        $this->mockSuccessfulHttpResponse();

        // 1 tip + 2 references = 3 save() calls
        $this->embeddingRepository->expects($this->exactly(3))->method('save');

        $this->commandTester->execute([]);
    }

    public function testExecuteSavesReferenceWithCorrectObjectAndExcerpt(): void
    {
        $tip       = $this->makeTip();
        $reference = $this->makeReference($tip, 1, 'Extracto de referencia.');
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([$reference]);
        $this->mockSuccessfulHttpResponse();

        $savedCalls = [];
        $this->embeddingRepository
            ->method('save')
            ->willReturnCallback(
                function (Tip $t, $ref, string $content) use (&$savedCalls): TipEmbedding {
                    $savedCalls[] = ['ref' => $ref, 'content' => $content];
                    return new TipEmbedding();
                }
            );

        $this->commandTester->execute([]);

        $this->assertNull($savedCalls[0]['ref']);
        $this->assertSame($reference, $savedCalls[1]['ref']);
        $this->assertSame('Extracto de referencia.', $savedCalls[1]['content']);
    }

    // ── Error handling ────────────────────────────────────────────────────────

    public function testExecuteReturnsFailureWhenHttpClientThrows(): void
    {
        $tip = $this->makeTip();
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->httpClient->method('request')->willThrowException(new \RuntimeException('LLM unreachable'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Indexed: 0, Skipped: 0, Errors: 1', $this->commandTester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenLlmResponseLacksEmbeddingField(): void
    {
        $tip = $this->makeTip();
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['not_embedding' => 'error']);
        $this->httpClient->method('request')->willReturn($response);

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Errors: 1', $this->commandTester->getDisplay());
    }

    public function testExecuteContinuesIndexingRemainingTipsAfterOneFails(): void
    {
        $failingTip    = $this->makeTip(1, 'Tip que falla');
        $succeedingTip = $this->makeTip(2, 'Tip que funciona');
        $this->tipRepository->method('findAll')->willReturn([$failingTip, $succeedingTip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([]);

        $goodResponse = $this->createMock(ResponseInterface::class);
        $goodResponse->method('toArray')->willReturn(['embedding' => [0.1]]);

        $callCount = 0;
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () use (&$callCount, $goodResponse) {
                ++$callCount;
                if ($callCount === 1) {
                    throw new \RuntimeException('Timeout');
                }
                return $goodResponse;
            });

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Indexed: 1, Skipped: 0, Errors: 1', $this->commandTester->getDisplay());
    }

    // ── Output ────────────────────────────────────────────────────────────────

    public function testExecuteOutputsReferenceCountForIndexedTip(): void
    {
        $tip  = $this->makeTip();
        $ref1 = $this->makeReference($tip, 1);
        $ref2 = $this->makeReference($tip, 2);
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([]);
        $this->referenceRepository->method('findByTip')->willReturn([$ref1, $ref2]);
        $this->mockSuccessfulHttpResponse();

        $this->commandTester->execute([]);

        $this->assertStringContainsString('2 reference(s)', $this->commandTester->getDisplay());
    }

    public function testExecuteOutputsSkippedMessageForAlreadyIndexedTip(): void
    {
        $tip = $this->makeTip();
        $this->tipRepository->method('findAll')->willReturn([$tip]);
        $this->embeddingRepository->method('findBy')->willReturn([$this->createMock(TipEmbedding::class)]);

        $this->commandTester->execute([]);

        $this->assertStringContainsString('skipped', $this->commandTester->getDisplay());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTip(
        int $id = 1,
        string $title = 'Ahorra más',
        string $description = 'Descripción del tip.',
    ): Tip {
        $tip = new Tip();
        $tip->setTitle($title);
        $tip->setDescription($description);
        $tip->setAuthor('Autor');
        $tip->setShortDescription('Descripción corta.');

        $ref = new \ReflectionProperty(Tip::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($tip, $id);

        return $tip;
    }

    private function makeReference(
        Tip $tip,
        int $id = 1,
        string $excerpt = 'Un extracto relevante del libro.',
    ): TipReference {
        $reference = new TipReference();
        $reference->setTip($tip);
        $reference->setTitle('El libro de referencia');
        $reference->setAuthor('Autor del libro');
        $reference->setType('book');
        $reference->setExcerpt($excerpt);

        $ref = new \ReflectionProperty(TipReference::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($reference, $id);

        return $reference;
    }

    private function mockSuccessfulHttpResponse(array $vector = [0.1, 0.2, 0.3]): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['embedding' => $vector]);
        $this->httpClient->method('request')->willReturn($response);
    }
}
