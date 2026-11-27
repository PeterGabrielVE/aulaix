<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;

/**
 * A student's health data, all optional (EST-R04.1). Seen only by whoever
 * manages students (EST-R04.2), never audited with its values (EST-R10.3).
 */
final readonly class HealthRecord
{
    public const int MAX_LENGTH = 1000;

    private function __construct(
        public ?string $allergies,
        public ?string $medicalConditions,
        public ?string $disability,
    ) {}

    public static function none(): self
    {
        return new self(null, null, null);
    }

    /**
     * @throws BusinessRuleViolations
     */
    public static function fromInput(?string $allergies, ?string $medicalConditions, ?string $disability): self
    {
        $values = [
            'allergies' => self::clean($allergies),
            'medical_conditions' => self::clean($medicalConditions),
            'disability' => self::clean($disability),
        ];
        $violations = [];

        foreach ($values as $field => $value) {
            if ($value !== null && mb_strlen($value) > self::MAX_LENGTH) {
                $violations[] = BusinessRuleViolation::onField($field, 'Este dato admite como máximo '.self::MAX_LENGTH.' caracteres.');
            }
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new self($values['allergies'], $values['medical_conditions'], $values['disability']);
    }

    /**
     * Without any health data: nothing to warn about (EST-R04.3).
     */
    public function isEmpty(): bool
    {
        return $this->allergies === null && $this->medicalConditions === null && $this->disability === null;
    }

    /**
     * @return array<string, ?string> By field.
     */
    public function fields(): array
    {
        return [
            'allergies' => $this->allergies,
            'medical_conditions' => $this->medicalConditions,
            'disability' => $this->disability,
        ];
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
