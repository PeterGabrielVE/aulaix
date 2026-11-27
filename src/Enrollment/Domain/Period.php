<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use DateTimeImmutable;
use LogicException;

/**
 * How long an enrollment lasts: from a date, until it ends for a reason.
 * Current while it has not ended.
 */
final readonly class Period
{
    private function __construct(
        public DateTimeImmutable $startsOn,
        public ?DateTimeImmutable $endsOn,
        public ?EndReason $endReason,
    ) {}

    public static function from(DateTimeImmutable $startsOn): self
    {
        return new self($startsOn->setTime(0, 0), null, null);
    }

    public static function reconstitute(DateTimeImmutable $startsOn, ?DateTimeImmutable $endsOn, ?EndReason $endReason): self
    {
        if (($endsOn === null) !== ($endReason === null)) {
            throw new LogicException('An enrollment has a reason to end if and only if it ended.');
        }

        return new self($startsOn, $endsOn, $endReason);
    }

    /**
     * @throws BusinessRuleViolation When the date is before the start.
     */
    public function endedOn(DateTimeImmutable $date, EndReason $reason): self
    {
        if (! $this->isCurrent()) {
            throw new LogicException('An enrollment ends once.');
        }

        $date = $date->setTime(0, 0);

        if ($date < $this->startsOn) {
            throw BusinessRuleViolation::onField('date', "La fecha no puede ser anterior al inicio de la inscripción ({$this->startsOn->format('d/m/Y')}).");
        }

        return new self($this->startsOn, $date, $reason);
    }

    public function isCurrent(): bool
    {
        return $this->endsOn === null;
    }
}
