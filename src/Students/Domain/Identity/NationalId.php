<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Identity;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A cédula de identidad of the SAIME: V (Venezuelan) or E (foreign) and a
 * number of 1 to 8 digits (EST-R02.2). Dots, dashes and spaces are typing
 * habits and are dropped; the number is kept without leading zeros.
 */
final readonly class NationalId
{
    private function __construct(
        public string $nationality,
        public string $number,
    ) {}

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromString(string $value, string $field = 'identification'): self
    {
        $typed = strtoupper((string) preg_replace('/[\s.\-]/', '', $value));

        if (preg_match('/^([VE])(\d{1,8})$/D', $typed, $parts) !== 1 || ltrim($parts[2], '0') === '') {
            throw BusinessRuleViolation::onField($field, 'La cédula debe ser V o E seguida de 1 a 8 dígitos.');
        }

        return new self($parts[1], ltrim($parts[2], '0'));
    }

    public function value(): string
    {
        return $this->nationality.$this->number;
    }

    /**
     * The number completed to 8 digits with leading zeros, as a cédula
     * escolar embeds it.
     */
    public function paddedNumber(): string
    {
        return str_pad($this->number, 8, '0', STR_PAD_LEFT);
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }
}
