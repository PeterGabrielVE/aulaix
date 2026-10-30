<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * How a pending subject stands in a year (RVP-R05.2, R05.3): studying until
 * its final grade after revision is decided with the third term closed;
 * then approved or failed. A failed one is still owed the year after.
 */
final class PendingStanding
{
    public const string STUDYING = 'studying';

    public const string APPROVED = 'approved';

    public const string FAILED = 'failed';

    public static function of(FinalAfterRevision $result, bool $thirdTermClosed): string
    {
        return match (true) {
            ! $thirdTermClosed => self::STUDYING,
            $result->passed() => self::APPROVED,
            $result->failed() => self::FAILED,
            default => self::STUDYING,
        };
    }
}
