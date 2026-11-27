<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Mandatory reason for voiding an issued document (SPEC-012 DOC-R06.1, R06.2).
 */
final readonly class VoidReason
{
    public const int MIN_LENGTH = 5;

    public const int MAX_LENGTH = 500;

    private function __construct(public string $value) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromString(string $raw): self
    {
        $trimmed = trim($raw);
        $length = mb_strlen($trimmed);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw BusinessRuleViolation::onField('reason', 'Indica un motivo de entre 5 y 500 caracteres.');
        }

        return new self($trimmed);
    }
}
