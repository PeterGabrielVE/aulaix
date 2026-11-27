<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use DateTimeImmutable;

/**
 * A withdrawal of the student (EST-R06.1 – R06.3): date, reason and an
 * optional note; the date of the reinstatement, if it came back (EST-R06.4).
 */
final readonly class Withdrawal
{
    public const int NOTE_MAX_LENGTH = 500;

    public function __construct(
        public DateTimeImmutable $withdrawnOn,
        public WithdrawalReason $reason,
        public ?string $note,
        public ?DateTimeImmutable $reinstatedOn = null,
    ) {}

    /**
     * @throws BusinessRuleViolations
     */
    public static function fromInput(?string $withdrawnOn, ?string $reason, ?string $note, DateTimeImmutable $today): self
    {
        $violations = [];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $withdrawnOn));
        $parsedReason = WithdrawalReason::tryFrom((string) $reason);
        $note = trim((string) $note) === '' ? null : trim((string) $note);

        if ($date === false) {
            $violations[] = BusinessRuleViolation::onField('withdrawn_on', 'Indica la fecha del retiro.');
        } elseif ($date > $today) {
            $violations[] = BusinessRuleViolation::onField('withdrawn_on', 'La fecha del retiro no puede ser posterior a hoy.');
        }

        if ($parsedReason === null) {
            $violations[] = BusinessRuleViolation::onField('reason', 'Elige el motivo del retiro.');
        }

        if ($note !== null && mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            $violations[] = BusinessRuleViolation::onField('note', 'La observación admite como máximo '.self::NOTE_MAX_LENGTH.' caracteres.');
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new self($date, $parsedReason, $note);
    }

    public function reinstatedOn(DateTimeImmutable $date): self
    {
        return new self($this->withdrawnOn, $this->reason, $this->note, $date);
    }
}
