<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * A number as a form sends it ("9,5", "15.75", 12) in hundredths, or null
 * when it is not a plain number with up to two decimals.
 */
final class Hundredths
{
    public static function parse(string|int|float|null $input): ?int
    {
        $text = str_replace(',', '.', trim((string) $input));

        if (preg_match('/^(\d{1,4})(?:\.(\d{1,2}))?$/', $text, $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    /**
     * With a comma, and its decimals only when it has them: 950 → "9,5".
     */
    public static function display(int $hundredths): string
    {
        $whole = intdiv($hundredths, 100);
        $decimals = rtrim(str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT), '0');

        return $decimals === '' ? (string) $whole : "{$whole},{$decimals}";
    }
}
