<?php

namespace App\ValueObject;

/**
 * Immutable value object representing the temporal period extracted by the LLM classifier.
 *
 * Encapsulates validation and normalisation of dates coming from the LLM service,
 * keeping that responsibility out of ChatService and FinancialContextService.
 * When the period is invalid or cannot be normalised, fromArray() returns null,
 * triggering the compact-snapshot fallback in HistoricalFinancialQueryService.
 */
final readonly class PeriodHint
{
    private const MAX_MONTHS_BACK = 24;

    private const MONTH_NAMES = [
        'enero' => '01', 'febrero' => '02', 'marzo' => '03',
        'abril' => '04', 'mayo' => '05', 'junio' => '06',
        'julio' => '07', 'agosto' => '08', 'septiembre' => '09',
        'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
    ];

    private function __construct(
        public readonly string $fromMonth,
        public readonly string $toMonth,
        public readonly ?string $category = null,
        /**
         * Set when the requested from_month predates the 24-month availability limit.
         * Stores the original value while fromMonth is clamped to the limit.
         * HistoricalFinancialQueryService uses this to prepend an explanatory note
         * so the LLM can inform the user about the data boundary.
         */
        public readonly ?string $truncatedFrom = null,
    ) {
    }

    /**
     * Builds and validates a PeriodHint from the raw array returned by the LLM classifier.
     *
     * The process has five defence layers:
     * 1. Minimum data presence check
     * 2. Format normalisation (e.g. "abril 2026", "04/2026", "2026-4" → "YYYY-MM")
     * 3. Semantic validation — rejects future start dates
     * 4. Upper-bound clamping — trims to_month to current month when it falls in the future
     * 5. Availability-limit clamping — if from_month predates MAX_MONTHS_BACK, clamps
     *    rather than rejecting and records the original value in $truncatedFrom.
     *
     * Returns null only for irreparable invalid data (unrecognised format, inverted range).
     * Exceeding the 24-month limit no longer causes a null return.
     */
    public static function fromArray(?array $data): ?self
    {
        if (empty($data) || empty($data['from_month']) || empty($data['to_month'])) {
            return null;
        }

        $from = self::normalizeMonth((string) $data['from_month']);
        $to   = self::normalizeMonth((string) $data['to_month']);

        if (null === $from || null === $to) {
            return null;
        }

        // A period starting in the future has no data.
        $currentMonth = (new \DateTime())->format('Y-m');
        if ($from > $currentMonth) {
            return null;
        }

        // Clamp the upper bound to the current month when it falls in the future
        // (e.g. "this year" queried in June produces to_month = December).
        if ($to > $currentMonth) {
            $to = $currentMonth;
        }

        // The range cannot be inverted after clamping.
        if ($from > $to) {
            return null;
        }

        // Clamp from_month to the availability limit instead of rejecting.
        // The original value is preserved in $truncatedFrom so the service can
        // prepend an explanatory note rather than silently returning a fallback.
        $truncatedFrom = null;
        $limit = (new \DateTime('first day of -' . self::MAX_MONTHS_BACK . ' months'))->format('Y-m');
        if ($from < $limit) {
            $truncatedFrom = $from;
            $from          = $limit;
        }

        // After clamping, the range could be inverted if to_month was also very old.
        if ($from > $to) {
            return null;
        }

        $category = isset($data['category']) && '' !== trim((string) $data['category'])
            ? trim((string) $data['category'])
            : null;

        return new self(
            fromMonth: $from,
            toMonth: $to,
            category: $category,
            truncatedFrom: $truncatedFrom,
        );
    }

    /**
     * Normalises alternative month formats that the LLM may produce.
     *
     * Accepts and fixes:
     * - "2026-04"    → already correct
     * - "2026-4"     → "2026-04"   (month without leading zero)
     * - "04-2026"    → "2026-04"   (inverted format with hyphen)
     * - "04/2026"    → "2026-04"   (inverted format with slash)
     * - "abril 2026" → "2026-04"   (Spanish month name + year)
     * - "2026 abril" → "2026-04"   (year + Spanish month name)
     *
     * Returns null for unrecognised formats, causing the caller to fall back.
     */
    private static function normalizeMonth(string $raw): ?string
    {
        $raw = trim($raw);

        // Case 1: already in canonical YYYY-MM format (e.g. "2026-04")
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $raw)) {
            return $raw;
        }

        // Case 2: month without leading zero — "2026-4"
        if (preg_match('/^(\d{4})-([1-9])$/', $raw, $m)) {
            return $m[1] . '-0' . $m[2];
        }

        // Case 3: inverted format — "04-2026" or "04/2026"
        if (preg_match('/^(0[1-9]|1[0-2])[-\/](\d{4})$/', $raw, $m)) {
            return $m[2] . '-' . $m[1];
        }

        // Case 4: Spanish month name followed by year — "abril 2026"
        $lower = strtolower($raw);
        if (preg_match('/^(\w+)\s+(\d{4})$/', $lower, $m)) {
            $month = self::MONTH_NAMES[$m[1]] ?? null;

            return null !== $month ? $m[2] . '-' . $month : null;
        }

        // Case 5: year followed by Spanish month name — "2026 abril"
        if (preg_match('/^(\d{4})\s+(\w+)$/', $lower, $m)) {
            $month = self::MONTH_NAMES[$m[2]] ?? null;

            return null !== $month ? $m[1] . '-' . $month : null;
        }

        return null;
    }

    /**
     * Returns a human-readable period description for use in the LLM context.
     * Examples: single month → "abril 2026"; range → "enero–marzo 2026".
     */
    public function toHumanReadable(): string
    {
        if ($this->fromMonth === $this->toMonth) {
            return $this->formatMonth($this->fromMonth);
        }

        [$fromYear] = explode('-', $this->fromMonth);
        [$toYear]   = explode('-', $this->toMonth);

        if ($fromYear === $toYear) {
            return $this->monthName($this->fromMonth) . '–' . $this->formatMonth($this->toMonth);
        }

        return $this->formatMonth($this->fromMonth) . '–' . $this->formatMonth($this->toMonth);
    }

    private function formatMonth(string $yearMonth): string
    {
        [$year, $month] = explode('-', $yearMonth);
        $names = array_flip(self::MONTH_NAMES);

        return ($names[$month] ?? $month) . ' ' . $year;
    }

    private function monthName(string $yearMonth): string
    {
        [, $month] = explode('-', $yearMonth);
        $names = array_flip(self::MONTH_NAMES);

        return $names[$month] ?? $month;
    }
}
