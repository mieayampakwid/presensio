<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money helper operating on 2-decimal-place strings via bcmath.
 * Avoids IEEE 754 float precision loss on financial calculations (spec 14 §Constraints).
 */
final class Money
{
    public const SCALE = 2;

    /**
     * Normalize amount into a standard 2-decimal-place string.
     *
     * @return numeric-string
     */
    public static function toDecimal(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        if (is_int($amount)) {
            /** @var numeric-string */
            return sprintf('%d.00', $amount);
        }

        if (is_float($amount)) {
            /** @var numeric-string */
            return number_format($amount, self::SCALE, '.', '');
        }

        $trimmed = trim((string) $amount);
        if (! is_numeric($trimmed)) {
            throw new InvalidArgumentException("Invalid monetary amount: {$amount}");
        }

        return bcadd($trimmed, '0', self::SCALE);
    }

    /**
     * Add two amounts: a + b
     */
    public static function add(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcadd(self::toDecimal($a), self::toDecimal($b), self::SCALE);
    }

    /**
     * Subtract two amounts: a - b
     */
    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::toDecimal($a), self::toDecimal($b), self::SCALE);
    }

    /**
     * Compare two amounts: returns -1 (a < b), 0 (a == b), 1 (a > b)
     */
    public static function cmp(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::toDecimal($a), self::toDecimal($b), self::SCALE);
    }

    /**
     * Check if amount is zero (0.00).
     */
    public static function isZero(string|int|float|null $amount): bool
    {
        return self::cmp($amount, '0.00') === 0;
    }

    /**
     * Check if a > b
     */
    public static function gt(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) > 0;
    }

    /**
     * Check if a >= b
     */
    public static function gte(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) >= 0;
    }

    /**
     * Check if a < b
     */
    public static function lt(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) < 0;
    }

    /**
     * Check if a <= b
     */
    public static function lte(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::cmp($a, $b) <= 0;
    }

    /**
     * Check if amount is strictly positive (> 0.00).
     */
    public static function isPositive(string|int|float|null $amount): bool
    {
        return self::gt($amount, '0.00');
    }

    /**
     * Check if amount is strictly negative (< 0.00).
     */
    public static function isNegative(string|int|float|null $amount): bool
    {
        return self::lt($amount, '0.00');
    }

    /**
     * Multiply amount by factor (e.g. percent or multiplier).
     */
    public static function mul(string|int|float|null $amount, string|int|float $factor): string
    {
        return bcmul(self::toDecimal($amount), self::toDecimal($factor), self::SCALE);
    }

    /**
     * Absolute value of amount.
     */
    public static function abs(string|int|float|null $amount): string
    {
        $dec = self::toDecimal($amount);

        return str_starts_with($dec, '-') ? substr($dec, 1) : $dec;
    }

    /**
     * Format monetary amount in Indonesian Rupiah style.
     * E.g. "Rp 350.000" or "-Rp 350.000", or without symbol "350.000".
     */
    public static function format(string|int|float|null $amount, bool $withSymbol = true): string
    {
        $dec = self::toDecimal($amount);
        $isNegative = str_starts_with($dec, '-');
        $unsigned = $isNegative ? substr($dec, 1) : $dec;

        [$integerPart, $fractionalPart] = explode('.', $unsigned);

        $formattedInteger = number_format((int) $integerPart, 0, ',', '.');
        $hasCents = $fractionalPart !== '00';

        $numberStr = $hasCents
            ? "{$formattedInteger},{$fractionalPart}"
            : $formattedInteger;

        if (! $withSymbol) {
            return $isNegative ? "-{$numberStr}" : $numberStr;
        }

        return $isNegative ? "-Rp {$numberStr}" : "Rp {$numberStr}";
    }
}
