<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A section's letter: one letter A to Z (EAC-R02.3).
 */
final readonly class SectionLetter
{
    private function __construct(public string $value) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromString(?string $value): self
    {
        $letter = strtoupper(trim((string) $value));

        if (preg_match('/^[A-Z]$/D', $letter) !== 1) {
            throw BusinessRuleViolation::onField('letter', 'La sección es una sola letra de la A a la Z.');
        }

        return new self($letter);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
