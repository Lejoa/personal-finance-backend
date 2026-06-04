<?php

namespace App\Tests\Unit\ValueObject;

use App\ValueObject\PeriodHint;
use PHPUnit\Framework\TestCase;

class PeriodHintTest extends TestCase
{
    // ── fromArray — valid inputs ─────────────────────────────────────────────

    public function testFromArrayReturnsHintForCanonicalFormat(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-03', 'to_month' => '2026-05']);

        $this->assertNotNull($hint);
        $this->assertSame('2026-03', $hint->fromMonth);
        $this->assertSame('2026-05', $hint->toMonth);
        $this->assertNull($hint->category);
        $this->assertNull($hint->truncatedFrom);
    }

    public function testFromArraySetsCategory(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-04', 'to_month' => '2026-04', 'category' => 'Comida']);

        $this->assertNotNull($hint);
        $this->assertSame('Comida', $hint->category);
    }

    public function testFromArrayNormalisesMissingLeadingZeroInMonth(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-4', 'to_month' => '2026-5']);

        $this->assertNotNull($hint);
        $this->assertSame('2026-04', $hint->fromMonth);
        $this->assertSame('2026-05', $hint->toMonth);
    }

    public function testFromArrayNormalisesInvertedFormatWithHyphen(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '04-2026', 'to_month' => '05-2026']);

        $this->assertNotNull($hint);
        $this->assertSame('2026-04', $hint->fromMonth);
        $this->assertSame('2026-05', $hint->toMonth);
    }

    public function testFromArrayNormalisesInvertedFormatWithSlash(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '04/2026', 'to_month' => '05/2026']);

        $this->assertNotNull($hint);
        $this->assertSame('2026-04', $hint->fromMonth);
        $this->assertSame('2026-05', $hint->toMonth);
    }

    public function testFromArrayNormalisesSpanishMonthNamePlusYear(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => 'abril 2026', 'to_month' => 'mayo 2026']);

        $this->assertNotNull($hint);
        $this->assertSame('2026-04', $hint->fromMonth);
        $this->assertSame('2026-05', $hint->toMonth);
    }

    public function testFromArrayNormalisesYearPlusSpanishMonthName(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026 abril', 'to_month' => '2026 mayo']);

        $this->assertNotNull($hint);
        $this->assertSame('2026-04', $hint->fromMonth);
        $this->assertSame('2026-05', $hint->toMonth);
    }

    public function testFromArrayClampsFutureToMonthToCurrentMonth(): void
    {
        $currentMonth = (new \DateTime())->format('Y-m');
        $futureMonth  = (new \DateTime('+3 months'))->format('Y-m');

        $hint = PeriodHint::fromArray(['from_month' => $currentMonth, 'to_month' => $futureMonth]);

        $this->assertNotNull($hint);
        $this->assertSame($currentMonth, $hint->toMonth);
    }

    // ── fromArray — clamping for old dates ───────────────────────────────────

    public function testFromArrayClampsFromMonthBeyond24MonthsAndSetsTruncatedFrom(): void
    {
        $veryOld  = '2020-01';
        $recentTo = (new \DateTime('first day of last month'))->format('Y-m');
        $limit    = (new \DateTime('first day of -24 months'))->format('Y-m');

        $hint = PeriodHint::fromArray(['from_month' => $veryOld, 'to_month' => $recentTo]);

        $this->assertNotNull($hint, 'A period beyond 24 months should be clamped, not rejected');
        $this->assertSame($limit, $hint->fromMonth, 'fromMonth must be clamped to the 24-month limit');
        $this->assertSame($veryOld, $hint->truncatedFrom, 'truncatedFrom must hold the original requested from_month');
    }

    public function testFromArrayReturnsTruncatedFromNullWhenNoClampingOccurs(): void
    {
        $hint = PeriodHint::fromArray([
            'from_month' => (new \DateTime('first day of -6 months'))->format('Y-m'),
            'to_month'   => (new \DateTime('first day of last month'))->format('Y-m'),
        ]);

        $this->assertNotNull($hint);
        $this->assertNull($hint->truncatedFrom);
    }

    // ── fromArray — invalid inputs ───────────────────────────────────────────

    public function testFromArrayReturnsNullForNullInput(): void
    {
        $this->assertNull(PeriodHint::fromArray(null));
    }

    public function testFromArrayReturnsNullForEmptyArray(): void
    {
        $this->assertNull(PeriodHint::fromArray([]));
    }

    public function testFromArrayReturnsNullWhenFromMonthMissing(): void
    {
        $this->assertNull(PeriodHint::fromArray(['to_month' => '2026-05']));
    }

    public function testFromArrayReturnsNullWhenToMonthMissing(): void
    {
        $this->assertNull(PeriodHint::fromArray(['from_month' => '2026-03']));
    }

    public function testFromArrayReturnsNullForUnrecognisedFormat(): void
    {
        $this->assertNull(PeriodHint::fromArray(['from_month' => 'Q1 2026', 'to_month' => '2026-05']));
    }

    public function testFromArrayReturnsNullForInvalidMonthNumber(): void
    {
        $this->assertNull(PeriodHint::fromArray(['from_month' => '2026-13', 'to_month' => '2026-05']));
    }

    public function testFromArrayReturnsNullWhenFromIsAfterTo(): void
    {
        $this->assertNull(PeriodHint::fromArray(['from_month' => '2026-06', 'to_month' => '2026-03']));
    }

    public function testFromArrayReturnsNullWhenFromMonthIsInTheFuture(): void
    {
        $future = (new \DateTime('+2 months'))->format('Y-m');
        $this->assertNull(PeriodHint::fromArray(['from_month' => $future, 'to_month' => $future]));
    }

    public function testFromArrayIgnoresEmptyStringCategory(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-04', 'to_month' => '2026-04', 'category' => '  ']);

        $this->assertNotNull($hint);
        $this->assertNull($hint->category);
    }

    // ── toHumanReadable ──────────────────────────────────────────────────────

    public function testToHumanReadableReturnsSingleMonthName(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-04', 'to_month' => '2026-04']);

        $this->assertSame('abril 2026', $hint->toHumanReadable());
    }

    public function testToHumanReadableReturnsSameYearRangeWithoutRepeatingYear(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2026-01', 'to_month' => '2026-03']);

        $readable = $hint->toHumanReadable();

        $this->assertStringContainsString('enero', $readable);
        $this->assertStringContainsString('marzo 2026', $readable);
    }

    public function testToHumanReadableReturnsCrossYearRangeWithBothYears(): void
    {
        $hint = PeriodHint::fromArray(['from_month' => '2025-11', 'to_month' => '2026-01']);

        $readable = $hint->toHumanReadable();

        $this->assertStringContainsString('2025', $readable);
        $this->assertStringContainsString('2026', $readable);
    }
}
