<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;

/**
 * How many students a section takes (EAC-R02.4): a whole number above zero,
 * up to the cap of its stage unless a reason is given (EAC-R02.8, R02.9).
 */
final readonly class Capacity
{
    public const int REASON_MAX_LENGTH = 500;

    private function __construct(
        public int $value,
        public ?string $reason,
        private int $cap,
    ) {}

    /**
     * @throws BusinessRuleViolations
     */
    public static function of(mixed $value, ?string $reason, Grade $grade): self
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);

        if ($number === false || $number < 1) {
            throw BusinessRuleViolations::of(BusinessRuleViolation::onField('capacity', 'El cupo debe ser un número entero mayor que cero.'));
        }

        $stage = $grade->stage();
        $reason = trim((string) $reason);

        if ($number <= $stage->capacityCap()) {
            return new self($number, null, $stage->capacityCap());
        }

        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw BusinessRuleViolations::of(BusinessRuleViolation::onField(
                'capacity_reason',
                "El cupo de {$stage->label()} es de hasta {$stage->capacityCap()}: indica el motivo para superarlo.",
            ));
        }

        return new self($number, $reason, $stage->capacityCap());
    }

    public function isOverCap(): bool
    {
        return $this->value > $this->cap;
    }
}
