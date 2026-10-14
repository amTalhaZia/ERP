<?php

namespace App\Support\Money;

/**
 * Exact decimal arithmetic for money using bcmath. Amounts are strings with
 * 4 internal decimals (matching DECIMAL(18,4) columns) and are rounded
 * half-up to the currency's minor units only at presentation/posting time.
 *
 *   Money::add('10.10', '0.20')      → "10.3000"
 *   Money::mul('3', '19.99')         → "59.9700"
 *   Money::round('2.34567', 2)       → "2.35"
 *   Money::percent('200', '7.5')     → "15.0000"
 *
 * Never use floats for amounts; parse request input through Money::normalize().
 */
final class Money
{
    public const SCALE = 4;

    public static function normalize(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return self::zero();
        }

        $string = is_float($value) ? number_format($value, self::SCALE, '.', '') : trim((string) $value);
        $string = str_replace([',', ' '], '', $string);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $string)) {
            throw new \InvalidArgumentException("Invalid monetary amount: {$value}");
        }

        return bcadd($string, '0', self::SCALE);
    }

    public static function zero(): string
    {
        return bcadd('0', '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        if (bccomp($b, '0', self::SCALE) === 0) {
            throw new \DivisionByZeroError('Division by zero in Money::div');
        }

        return bcdiv($a, $b, self::SCALE);
    }

    /** $rate as percentage, e.g. '7.5' for 7.5 %. */
    public static function percent(string $amount, string $rate): string
    {
        return bcdiv(bcmul($amount, $rate, self::SCALE + 2), '100', self::SCALE);
    }

    /** @param  array<int, string>  $amounts */
    public static function sum(array $amounts): string
    {
        return array_reduce($amounts, fn (string $carry, string $x) => bcadd($carry, $x, self::SCALE), self::zero());
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isZero(string $a): bool
    {
        return bccomp($a, '0', self::SCALE) === 0;
    }

    public static function isNegative(string $a): bool
    {
        return bccomp($a, '0', self::SCALE) < 0;
    }

    public static function abs(string $a): string
    {
        return self::isNegative($a) ? bcmul($a, '-1', self::SCALE) : bcadd($a, '0', self::SCALE);
    }

    public static function negate(string $a): string
    {
        return bcmul($a, '-1', self::SCALE);
    }

    /** Round half-up to $decimals (currency minor units). */
    public static function round(string $amount, int $decimals = 2): string
    {
        $factor = bcpow('10', (string) $decimals, 0);
        $scaled = bcmul($amount, $factor, self::SCALE + 2);
        $adjust = self::isNegative($scaled) ? '-0.5' : '0.5';
        $rounded = bcadd($scaled, $adjust, 0);

        return bcdiv($rounded, $factor, $decimals);
    }

    public static function equals(string $a, string $b): bool
    {
        return self::compare($a, $b) === 0;
    }
}
