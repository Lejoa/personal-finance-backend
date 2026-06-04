<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\ValueObject\PeriodHint;

/**
 * Queries and formats historical financial data for arbitrary date ranges.
 *
 * Single responsibility: given a user and a PeriodHint, executes the relevant
 * SQL queries and serialises the results as human-readable text for the LLM.
 * Does not classify intent, build the base financial context, or call the LLM.
 *
 * Three execution paths:
 * - PeriodHint with category  → spending for that category by month over the range
 * - PeriodHint without category → income + expenses + savings rate by month + category breakdown
 * - PeriodHint null            → compact snapshot of the last N months (fallback)
 */
class HistoricalFinancialQueryService
{
    private const COMPACT_SNAPSHOT_MONTHS = 6;

    public function __construct(
        private readonly TransactionRepository $transactionRepository,
    ) {
    }

    /**
     * Main entry point. Delegates to the appropriate method based on the hint.
     *
     * When the PeriodHint was clamped (the user requested data older than the 24-month
     * availability limit), a note is prepended so the LLM can inform the user honestly
     * rather than answering as if the context covered the full requested period.
     */
    public function buildContext(User $user, ?PeriodHint $hint): string
    {
        if (null === $hint) {
            return $this->buildCompactSnapshot($user, self::COMPACT_SNAPSHOT_MONTHS);
        }

        $context = null !== $hint->category
            ? $this->buildCategoryContext($user, $hint)
            : $this->buildTotalsContext($user, $hint);

        if (null !== $hint->truncatedFrom) {
            $originalFrom  = $this->formatMonth($hint->truncatedFrom);
            $availableFrom = $this->formatMonth($hint->fromMonth);
            $prefix = "Nota: los datos solicitados desde {$originalFrom} no están disponibles. "
                    . "Solo hay registros a partir de {$availableFrom}.\n\n";
            $context = $prefix . $context;
        }

        return $context;
    }

    /**
     * Spending for a specific category by month over the PeriodHint range.
     *
     * Example output:
     *   Datos históricos — Comida (abril 2026):
     *   - abril 2026: $850.000 COP
     *   Total en el período: $850.000 COP
     */
    private function buildCategoryContext(User $user, PeriodHint $hint): string
    {
        $rows = $this->transactionRepository->getMonthlyCategorySpendingForRange(
            $user,
            $hint->fromMonth,
            $hint->toMonth,
            $hint->category,
        );

        $period = $hint->toHumanReadable();

        if (empty($rows)) {
            return "Sin registros de {$hint->category} en el período {$period}.";
        }

        $lines = ["Datos históricos — {$hint->category} ({$period}):"];
        $total = 0.0;

        foreach ($rows as $row) {
            $formatted = number_format($row['total'], 0, ',', '.');
            $lines[]   = '- ' . $this->formatMonth($row['month']) . ": \${$formatted} COP";
            $total     += $row['total'];
        }

        $totalFormatted = number_format($total, 0, ',', '.');
        $lines[] = "Total en el período: \${$totalFormatted} COP";

        return implode("\n", $lines);
    }

    /**
     * Income, expenses and savings rate by month over the PeriodHint range,
     * plus a category breakdown aggregated across all months in the range.
     *
     * The category breakdown is always included because the same period may be
     * used to answer both "how much did I spend in total?" and "which category
     * was highest?", and the LLM needs both data sets in the same context.
     *
     * Example output (single month):
     *   Datos históricos (abril 2026):
     *   - abril 2026: ingresos $4.200.000 COP | gastos $2.900.000 COP | ahorro 31,0%
     *   Total período: ingresos $4.200.000 COP | gastos $2.900.000 COP | ahorro 31,0%
     *
     *   Desglose de gastos por categoría (abril 2026):
     *   - Comida: $850.000 COP
     *   - Transporte: $320.000 COP
     */
    private function buildTotalsContext(User $user, PeriodHint $hint): string
    {
        $rows = $this->transactionRepository->getMonthlyTotalsForRange(
            $user,
            $hint->fromMonth,
            $hint->toMonth,
        );

        $period = $hint->toHumanReadable();

        if (empty($rows)) {
            return "Sin registros en el período {$period}.";
        }

        $lines         = ["Datos históricos ({$period}):"];
        $totalIncome   = 0.0;
        $totalExpenses = 0.0;

        foreach ($rows as $row) {
            $income   = number_format($row['income'], 0, ',', '.');
            $expenses = number_format($row['expenses'], 0, ',', '.');
            $savings  = number_format($row['savings_rate'], 1, ',', '');
            $lines[]  = '- ' . $this->formatMonth($row['month'])
                      . ": ingresos \${$income} COP | gastos \${$expenses} COP | ahorro {$savings}%";
            $totalIncome   += $row['income'];
            $totalExpenses += $row['expenses'];
        }

        $totalSavings = $totalIncome > 0
            ? round((($totalIncome - $totalExpenses) / $totalIncome) * 100, 1)
            : 0.0;
        $incomeSum   = number_format($totalIncome, 0, ',', '.');
        $expensesSum = number_format($totalExpenses, 0, ',', '.');
        $savingsSum  = number_format($totalSavings, 1, ',', '');

        $lines[] = "Total período: ingresos \${$incomeSum} COP | gastos \${$expensesSum} COP | ahorro {$savingsSum}%";

        // Append category breakdown so the LLM can answer follow-up questions
        // like "which category had the highest spend?" over the same period.
        $categoryRows = $this->transactionRepository->getMonthlyCategorySpendingForRange(
            $user,
            $hint->fromMonth,
            $hint->toMonth,
        );

        if (!empty($categoryRows)) {
            // Aggregate per category, summing across all months in the range.
            $categoryTotals = [];
            foreach ($categoryRows as $row) {
                $cat = $row['category'];
                $categoryTotals[$cat] = ($categoryTotals[$cat] ?? 0.0) + $row['total'];
            }
            arsort($categoryTotals);

            $lines[] = '';
            $lines[] = "Desglose de gastos por categoría ({$period}):";
            foreach ($categoryTotals as $category => $total) {
                $formatted = number_format($total, 0, ',', '.');
                $lines[] = "- {$category}: \${$formatted} COP";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Compact summary of the last N months. Used as a fallback when the classifier
     * detects historical intent but cannot extract a concrete period (PeriodHint null).
     *
     * Example output:
     *   Resumen últimos 6 meses:
     *   - diciembre 2025: ingresos $4.000.000 | gastos $2.800.000 | ahorro 30,0%
     *   ...
     */
    private function buildCompactSnapshot(User $user, int $months): string
    {
        $to   = (new \DateTime('first day of last month'))->format('Y-m');
        $from = (new \DateTime("first day of -{$months} months"))->format('Y-m');

        $rows = $this->transactionRepository->getMonthlyTotalsForRange($user, $from, $to);

        if (empty($rows)) {
            return '';
        }

        $lines = ["Resumen últimos {$months} meses:"];

        foreach ($rows as $row) {
            $income   = number_format($row['income'], 0, ',', '.');
            $expenses = number_format($row['expenses'], 0, ',', '.');
            $savings  = number_format($row['savings_rate'], 1, ',', '');
            $lines[]  = '- ' . $this->formatMonth($row['month'])
                      . ": ingresos \${$income} | gastos \${$expenses} | ahorro {$savings}%";
        }

        return implode("\n", $lines);
    }

    private function formatMonth(string $yearMonth): string
    {
        [$year, $month] = explode('-', $yearMonth);

        $names = [
            '01' => 'enero', '02' => 'febrero', '03' => 'marzo',
            '04' => 'abril', '05' => 'mayo', '06' => 'junio',
            '07' => 'julio', '08' => 'agosto', '09' => 'septiembre',
            '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre',
        ];

        return ($names[$month] ?? $month) . ' ' . $year;
    }
}
