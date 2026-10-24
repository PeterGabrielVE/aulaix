<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Identity;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The passport of a foreign guardian without a cédula (EST-R03.2). It can not
 * form a cédula escolar.
 */
final readonly class Passport
{
    private function __construct(public string $value) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromString(string $value, string $field = 'document'): self
    {
        $typed = strtoupper((string) preg_replace('/\s/', '', $value));

        if (preg_match('/^[A-Z0-9]{5,20}$/D', $typed) !== 1) {
            throw BusinessRuleViolation::onField($field, 'El pasaporte debe tener entre 5 y 20 letras o dígitos.');
        }

        return new self($typed);
    }
}
