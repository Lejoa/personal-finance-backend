<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/transactions')]
#[IsGranted('ROLE_USER')]
class TransactionExportController extends AbstractController
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {
    }

    /**
     * Streams all matching transactions as a UTF-8 CSV file for download.
     * Supports the same filters as the list endpoint: type, startDate, endDate, categoryId.
     * Appends a summary row with totals for income and expenses.
     * Must be declared before /{id} to prevent Symfony from matching "export" as an id.
     */
    #[Route('/export/csv', name: 'api_transaction_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user       = $this->getUser();
        $type       = $request->query->get('type');
        $startDate  = $request->query->get('startDate');
        $endDate    = $request->query->get('endDate');
        $categoryId = $request->query->get('categoryId');

        $transactions = $this->transactionService->getUserTransactions(
            $user, $type, $startDate, $endDate, null, null
        );

        if ($categoryId !== null && $categoryId !== '') {
            $transactions = array_values(array_filter(
                $transactions,
                fn(Transaction $t) => $t->getCategory()?->getId() === (int) $categoryId
            ));
        }

        $filename = 'transacciones_' . (new \DateTimeImmutable())->format('Y-m-d') . '.csv';

        $response = new StreamedResponse(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Fecha', 'Nombre', 'Tipo', 'Categoría', 'Monto', 'Nota', 'Fuente']);

            $totalIncome   = 0.0;
            $totalExpenses = 0.0;

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->getDate()?->format('d/m/Y') ?? '',
                    $t->getName() ?? '',
                    $t->getType() === 'ingreso' ? 'Ingreso' : 'Gasto',
                    $t->getCategory()?->getName() ?? '',
                    $t->getAmount() ?? 0,
                    $t->getNote() ?? '',
                    $t->getSource() === 'sms' ? 'SMS' : 'Manual',
                ]);

                if ($t->getType() === 'ingreso') {
                    $totalIncome += $t->getAmount() ?? 0;
                } else {
                    $totalExpenses += $t->getAmount() ?? 0;
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['', '', '', 'Total ingresos', $totalIncome, '', '']);
            fputcsv($handle, ['', '', '', 'Total gastos',   $totalExpenses, '', '']);

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
