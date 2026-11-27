<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Identity;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * How a guardian is identified: a cédula or, if foreign without one, a
 * passport (EST-R03.2).
 */
final readonly class GuardianDocument
{
    public const string NATIONAL_ID = 'national_id';

    public const string PASSPORT = 'passport';

    private function __construct(
        public string $type,
        private NationalId|Passport $document,
    ) {}

    public static function ofNationalId(NationalId $id): self
    {
        return new self(self::NATIONAL_ID, $id);
    }

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromInput(string $type, string $value, string $field = 'document'): self
    {
        return match ($type) {
            self::NATIONAL_ID => self::ofNationalId(NationalId::fromString($value, $field)),
            self::PASSPORT => new self(self::PASSPORT, Passport::fromString($value, $field)),
            default => throw BusinessRuleViolation::onField($field.'_type', 'Elige cédula o pasaporte.'),
        };
    }

    public function value(): string
    {
        return $this->document instanceof Passport ? $this->document->value : $this->document->value();
    }

    /**
     * The cédula, or null for a passport: only a cédula forms a cédula
     * escolar.
     */
    public function nationalId(): ?NationalId
    {
        return $this->document instanceof NationalId ? $this->document : null;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value() === $other->value();
    }
}
