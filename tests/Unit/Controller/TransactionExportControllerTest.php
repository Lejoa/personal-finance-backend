<?php

namespace App\Tests\Unit\Controller;

use App\Controller\TransactionExportController;
use App\Service\TransactionService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportControllerTest extends AbstractControllerTestCase
{
    private TransactionService&MockObject $transactionService;
    private TransactionExportController&MockObject $controller;

    protected function setUp(): void
    {
        $this->transactionService = $this->createMock(TransactionService::class);

        $this->controller = $this->makeControllerMock(
            TransactionExportController::class,
            [$this->transactionService],
        );
    }

    public function testExportCsvReturnsStreamedResponse(): void
    {
        $this->transactionService->method('getUserTransactions')->willReturn([]);

        $response = $this->controller->exportCsv(new Request());

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testExportCsvHasCorrectContentTypeHeader(): void
    {
        $this->transactionService->method('getUserTransactions')->willReturn([]);

        $response = $this->controller->exportCsv(new Request());

        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function testExportCsvHasAttachmentDispositionHeader(): void
    {
        $this->transactionService->method('getUserTransactions')->willReturn([]);

        $response = $this->controller->exportCsv(new Request());

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('transacciones_', $response->headers->get('Content-Disposition'));
    }

    public function testExportCsvOutputsHeaderRow(): void
    {
        $this->transactionService->method('getUserTransactions')->willReturn([]);

        $response = $this->controller->exportCsv(new Request());

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertStringContainsString('Fecha', $output);
        $this->assertStringContainsString('Nombre', $output);
        $this->assertStringContainsString('Tipo', $output);
        $this->assertStringContainsString('Monto', $output);
    }

    public function testExportCsvOutputsTotalsRow(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);

        $transaction = $this->makeTransaction($user);
        $transaction->setType('ingreso');
        $transaction->setAmount(1000.0);

        $this->transactionService->method('getUserTransactions')->willReturn([$transaction]);

        $response = $this->controller->exportCsv(new Request());

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertStringContainsString('Total ingresos', $output);
        $this->assertStringContainsString('Total gastos', $output);
    }
}
