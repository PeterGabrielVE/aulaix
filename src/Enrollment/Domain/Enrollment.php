<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use DateTimeImmutable;
use LogicException;

/**
 * A student in a section of a school year, for a period (MAT-R01): it moves
 * to another section or grade by ending and opening the next one (MAT-R04),
 * ends with a withdrawal or a transfer (MAT-R05) and is annulled when made
 * by mistake (MAT-R14). Once ended, it does not change.
 */
final class Enrollment
{
    public const int REASON_MAX_LENGTH = 500;

    public const int HOURS_TO_ANNUL = 48;

    private ?PromotionResult $result = null;

    private ?DateTimeImmutable $resultRecordedAt = null;

    private ?int $resultRecordedBy = null;

    private function __construct(
        private ?EnrollmentId $id,
        private readonly int $studentId,
        private readonly int $schoolYearId,
        private readonly Placement $placement,
        private Period $period,
        private ?string $endNote,
        private readonly ?string $overCapacityReason,
        private readonly ?AgeException $ageException,
        private readonly DateTimeImmutable $registeredAt,
        private readonly int $registeredBy,
        private ?Annulment $annulment,
        private readonly ?int $previousId,
    ) {}

    /**
     * @throws BusinessRuleViolation When the start is after today.
     */
    public static function enroll(
        int $studentId,
        int $schoolYearId,
        Placement $placement,
        DateTimeImmutable $startsOn,
        DateTimeImmutable $today,
        ?string $overCapacityReason,
        ?AgeException $ageException,
        DateTimeImmutable $registeredAt,
        int $registeredBy,
    ): self {
        if ($startsOn->setTime(0, 0) > $today->setTime(0, 0)) {
            throw BusinessRuleViolation::onField('date', 'La fecha de inscripción no puede ser posterior a hoy.');
        }

        return new self(null, $studentId, $schoolYearId, $placement, Period::from($startsOn), null, $overCapacityReason, $ageException, $registeredAt, $registeredBy, null, null);
    }

    public static function reconstitute(
        EnrollmentId $id,
        int $studentId,
        int $schoolYearId,
        Placement $placement,
        Period $period,
        ?string $endNote,
        ?string $overCapacityReason,
        ?AgeException $ageException,
        DateTimeImmutable $registeredAt,
        int $registeredBy,
        ?Annulment $annulment,
        ?int $previousId,
        ?PromotionResult $result = null,
        ?DateTimeImmutable $resultRecordedAt = null,
        ?int $resultRecordedBy = null,
    ): self {
        $enrollment = new self($id, $studentId, $schoolYearId, $placement, $period, $endNote, $overCapacityReason, $ageException, $registeredAt, $registeredBy, $annulment, $previousId);
        $enrollment->result = $result;
        $enrollment->resultRecordedAt = $resultRecordedAt;
        $enrollment->resultRecordedBy = $resultRecordedBy;

        return $enrollment;
    }

    /**
     * The result of the year of a current enrollment, checked by the rules
     * of its level (MAT-R06.3 – R06.7); a new one replaces it (MAT-R06.8).
     *
     * @param  list<int>  $curriculum  Subject ids of its grade that year.
     *
     * @throws BusinessRuleViolation
     */
    public function recordResult(PromotionResult $result, array $curriculum, DateTimeImmutable $at, int $by): void
    {
        $this->ensureCurrent();
        PromotionRules::check($this->placement->level, $result, $curriculum);

        $this->result = $result;
        $this->resultRecordedAt = $at;
        $this->resultRecordedBy = $by;
    }

    public function result(): ?PromotionResult
    {
        return $this->result;
    }

    public function resultRecordedAt(): ?DateTimeImmutable
    {
        return $this->resultRecordedAt;
    }

    public function resultRecordedBy(): ?int
    {
        return $this->resultRecordedBy;
    }

    /**
     * Promoted from the last year with no pending subjects (MAT-R15.1).
     */
    public function graduates(): bool
    {
        return $this->result !== null && PromotionRules::graduates($this->placement->grade, $this->result);
    }

    /**
     * Promoted from the last year with pending subjects (MAT-R15.2).
     */
    public function waitsToGraduate(): bool
    {
        return $this->result !== null && PromotionRules::waitsToGraduate($this->placement->grade, $this->result);
    }

    /**
     * Ends this enrollment on $date and opens the next one, in $target, from
     * the same date (MAT-R04.1, R04.7). A change of grade needs a directive
     * (MAT-R04.8) and carries its own age exception; a change of section
     * keeps this one's.
     *
     * @throws BusinessRuleViolation
     */
    public function moveTo(
        Placement $target,
        DateTimeImmutable $date,
        ?string $reason,
        DateTimeImmutable $today,
        ?string $overCapacityReason,
        ?AgeException $ageException,
        bool $byDirective,
        DateTimeImmutable $registeredAt,
        int $registeredBy,
    ): self {
        $this->ensureCurrent();

        $reason = trim((string) $reason);

        if ($reason === '' || mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw BusinessRuleViolation::onField('reason', 'Indica el motivo del cambio.');
        }

        if ($target->sectionId === $this->placement->sectionId) {
            throw BusinessRuleViolation::onField('section', 'El estudiante ya está en esa sección.');
        }

        $changesGrade = ! $target->sameGradeAs($this->placement);

        if ($changesGrade && ! $byDirective) {
            throw BusinessRuleViolation::onField('section', 'El cambio de grado lo aprueba un directivo.');
        }

        if ($date->setTime(0, 0) > $today->setTime(0, 0)) {
            throw BusinessRuleViolation::onField('date', 'La fecha no puede ser posterior a hoy.');
        }

        $this->period = $this->period->endedOn($date, $changesGrade ? EndReason::GradeChange : EndReason::SectionChange);
        $this->endNote = $reason;

        return new self(
            null,
            $this->studentId,
            $this->schoolYearId,
            $target,
            Period::from($date),
            null,
            $overCapacityReason,
            $changesGrade ? $ageException : $this->ageException,
            $registeredAt,
            $registeredBy,
            null,
            $this->id()?->value ?? throw new LogicException('An enrollment moves once saved.'),
        );
    }

    /**
     * Ends it for a withdrawal or a transfer (MAT-R05.1, R05.2): on $date,
     * or on its start when it had not started yet.
     */
    public function end(DateTimeImmutable $date, EndReason $reason): void
    {
        $this->ensureCurrent();

        $this->period = $this->period->endedOn(max($date->setTime(0, 0), $this->period->startsOn), $reason);
    }

    /**
     * Annuls an enrollment made by mistake (MAT-R14.1). After 48 hours, or
     * once it has grades, only a directive does (MAT-R14.3).
     *
     * @throws BusinessRuleViolation
     */
    public function annul(?AnnulmentReason $reason, ?string $note, int $annulledBy, DateTimeImmutable $now, bool $byDirective, bool $hasGrades): void
    {
        $this->ensureCurrent();

        if ($this->result !== null) {
            throw AnnulmentNotAllowed::withResult();
        }

        if (! $byDirective && $hasGrades) {
            throw AnnulmentNotAllowed::graded();
        }

        if (! $byDirective && $now->getTimestamp() - $this->registeredAt->getTimestamp() > self::HOURS_TO_ANNUL * 3600) {
            throw AnnulmentNotAllowed::late();
        }

        $this->annulment = Annulment::of($reason, $note, $annulledBy, $now);
        $this->period = $this->period->endedOn($this->period->startsOn, EndReason::Annulled);
    }

    public function identify(EnrollmentId $id): void
    {
        $this->id = $id;
    }

    public function id(): ?EnrollmentId
    {
        return $this->id;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function schoolYearId(): int
    {
        return $this->schoolYearId;
    }

    public function placement(): Placement
    {
        return $this->placement;
    }

    public function period(): Period
    {
        return $this->period;
    }

    public function isCurrent(): bool
    {
        return $this->period->isCurrent();
    }

    /**
     * The reason given for a change of section or grade.
     */
    public function endNote(): ?string
    {
        return $this->endNote;
    }

    public function overCapacityReason(): ?string
    {
        return $this->overCapacityReason;
    }

    public function ageException(): ?AgeException
    {
        return $this->ageException;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function registeredBy(): int
    {
        return $this->registeredBy;
    }

    public function annulment(): ?Annulment
    {
        return $this->annulment;
    }

    public function previousId(): ?int
    {
        return $this->previousId;
    }

    private function ensureCurrent(): void
    {
        if (! $this->isCurrent()) {
            throw BusinessRuleViolation::onField('enrollment', 'La inscripción ya terminó.');
        }
    }
}
