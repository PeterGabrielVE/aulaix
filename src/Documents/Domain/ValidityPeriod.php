<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use DateTimeImmutable;

/**
 * Expiration window for time-sensitive certificates (SPEC-012 DOC-R04.1,
 * R04.2, R04.4, R04.6): 90 days by default, configurable between 1 and 365.
 */
final readonly class ValidityPeriod
{
    public const int DEFAULT_DAYS = 90;

    public const int MIN_DAYS = 1;

    public const int MAX_DAYS = 365;

    private function __construct(public int $days) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function ofDays(?int $days): self
    {
        $resolved = $days ?? self::DEFAULT_DAYS;

        if ($resolved < self::MIN_DAYS || $resolved > self::MAX_DAYS) {
            throw BusinessRuleViolation::onField('validity_days', 'La vigencia debe estar entre 1 y 365 días.');
        }

        return new self($resolved);
    }

    public function expiresAt(DateTimeImmutable $issuedAt): DateTimeImmutable
    {
        return $issuedAt->modify("+{$this->days} days");
    }
}
