<?php

namespace App\Services\StudentImport;

use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * Normalizes date-of-birth cell values to ISO Y-m-d strings.
 *
 * Supported inputs, tried in order:
 *  - Excel serial numbers 20000–60000 (1954–2064) — numeric cells only
 *  - day-first dd/mm/yyyy, d/m/yyyy, dd-mm-yyyy (day-first is the standing
 *    assumption; US-style month-first strings are rejected)
 *  - ISO yyyy-mm-dd
 * Years before 1900 or in the future are rejected.
 */
class DateNormalizer
{
    private const EXCEL_EPOCH = '1899-12-30';

    private const MIN_SERIAL = 20000;

    private const MAX_SERIAL = 60000;

    /**
     * @return string|null ISO date, or null when unparseable.
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return self::fromExcelSerial((int) $value);
        }

        // Day-first: dd/mm/yyyy or dd-mm-yyyy.
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $value, $m) === 1) {
            return self::fromComponents((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // ISO: yyyy-mm-dd.
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) === 1) {
            return self::fromComponents((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    private static function fromComponents(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = CarbonImmutable::create($year, $month, $day, 0, 0, 0, new DateTimeZone('UTC'));

        if (! self::isPlausible($date)) {
            return null;
        }

        return $date->toDateString();
    }

    private static function fromExcelSerial(int $serial): ?string
    {
        if ($serial < self::MIN_SERIAL || $serial > self::MAX_SERIAL) {
            return null;
        }

        $date = CarbonImmutable::parse(self::EXCEL_EPOCH, new DateTimeZone('UTC'))->addDays($serial);

        if (! self::isPlausible($date)) {
            return null;
        }

        return $date->toDateString();
    }

    private static function isPlausible(CarbonImmutable $date): bool
    {
        return $date->year >= 1900 && $date->isPast();
    }
}
