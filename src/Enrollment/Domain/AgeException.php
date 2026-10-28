<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A directive's approval to enroll a student outside the age range of the
 * grade, with a justification (MAT-R03.4, R03.5).
 */
final readonly class AgeException
{
    public const int JUSTIFICATION_MAX_LENGTH = 500;

    private function __construct(
        public string $justification,
        public int $approvedBy,
    ) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function of(?string $justification, int $approvedBy, bool $byDirective): self
    {
        if (! $byDirective) {
            throw BusinessRuleViolation::onField('age_exception', 'Solo un directivo puede aprobar una inscripción fuera del rango de edad.');
        }

        $justification = trim((string) $justification);

        if ($justification === '' || mb_strlen($justification) > self::JUSTIFICATION_MAX_LENGTH) {
            throw BusinessRuleViolation::onField('age_exception', 'Indica la justificación de la excepción de edad.');
        }

        return new self($justification, $approvedBy);
    }

    public static function reconstitute(string $justification, int $approvedBy): self
    {
        return new self($justification, $approvedBy);
    }
}
