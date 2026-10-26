<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * An operator's email, lower-cased: unique among operators, unrelated to
 * the accounts of the institutions (ADR 0010).
 */
final readonly class OperatorEmail
{
    private const int MAX_LENGTH = 255;

    private function __construct(public string $value) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromString(string $value): self
    {
        $email = mb_strtolower(trim($value));

        if (strlen($email) > self::MAX_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw BusinessRuleViolation::onField('email', 'El correo no es válido.');
        }

        return new self($email);
    }
}
