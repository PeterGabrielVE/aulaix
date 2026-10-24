<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Identity;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * How a student is identified: a cédula de identidad or, while there is
 * none, a cédula escolar (EST-R02.1).
 */
final readonly class StudentIdentification
{
    public const string NATIONAL_ID = 'national_id';

    public const string SCHOOL_ID = 'school_id';

    private function __construct(
        public string $type,
        private NationalId|SchoolId $id,
    ) {}

    public static function ofNationalId(NationalId $id): self
    {
        return new self(self::NATIONAL_ID, $id);
    }

    public static function ofSchoolId(SchoolId $id): self
    {
        return new self(self::SCHOOL_ID, $id);
    }

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromInput(string $type, string $value): self
    {
        return match ($type) {
            self::NATIONAL_ID => self::ofNationalId(NationalId::fromString($value)),
            self::SCHOOL_ID => self::ofSchoolId(SchoolId::fromString($value)),
            default => throw BusinessRuleViolation::onField('identification_type', 'Elige cédula de identidad o cédula escolar.'),
        };
    }

    /**
     * Normalized, as stored and searched: V30123456, V11915432100.
     */
    public function value(): string
    {
        return $this->id instanceof SchoolId ? $this->id->value : $this->id->value();
    }

    public function schoolId(): ?SchoolId
    {
        return $this->id instanceof SchoolId ? $this->id : null;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value() === $other->value();
    }
}
