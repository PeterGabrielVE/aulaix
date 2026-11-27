<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Identity;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A cédula escolar of the MPPE, for a student without a cédula de identidad
 * yet: 12 characters N T AA CCCCCCCC (EST-R02.3).
 *
 * - N: nationality of the parent whose cédula is embedded (V or E);
 * - T: birth type, 1 single, 2 first twin, 3 second twin, 4 triplet or later;
 * - AA: last two digits of the birth year;
 * - C: the parent's cédula, completed to 8 digits with leading zeros.
 */
final readonly class SchoolId
{
    private function __construct(public string $value) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromString(string $value, string $field = 'identification'): self
    {
        $typed = strtoupper((string) preg_replace('/[\s.\-]/', '', $value));

        if (preg_match('/^[VE][1-4]\d{2}(\d{8})$/D', $typed, $parts) !== 1 || ltrim($parts[1], '0') === '') {
            throw BusinessRuleViolation::onField($field, 'La cédula escolar debe tener 12 caracteres: V o E, el tipo de parto (1 a 4), los dos últimos dígitos del año de nacimiento y los 8 dígitos de la cédula de la madre o el padre.');
        }

        return new self($typed);
    }

    public function nationality(): string
    {
        return $this->value[0];
    }

    public function birthType(): int
    {
        return (int) $this->value[1];
    }

    public function birthYearSuffix(): string
    {
        return substr($this->value, 2, 2);
    }

    /**
     * The cédula of the parent it was formed with (EST-R02.5).
     */
    public function embeddedNationalId(): NationalId
    {
        return NationalId::fromString($this->nationality().substr($this->value, 4));
    }
}
