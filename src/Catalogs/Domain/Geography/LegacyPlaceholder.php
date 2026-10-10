<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain\Geography;

/**
 * The first version of the geographic catalog held one placeholder
 * municipality and parish per state, with states coded VE-01 … VE-24. Those
 * codes match none of the official ones, so synchronizing removes them
 * (CAT-R03.2) rather than leaving duplicates behind.
 */
final class LegacyPlaceholder
{
    /**
     * Valid both as a PCRE body and as a PostgreSQL regular expression.
     */
    public const STATE_CODE_PATTERN = '^VE-[0-9]{2}$';

    public static function isPlaceholderState(string $code): bool
    {
        return preg_match('/'.self::STATE_CODE_PATTERN.'/', $code) === 1;
    }
}
