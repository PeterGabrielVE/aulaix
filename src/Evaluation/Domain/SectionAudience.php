<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * The students of a section, by their enrollment periods (EVA-R02.5,
 * R02.8): an activity applies to whoever was in the section on its date.
 */
final readonly class SectionAudience implements ActivityAudience
{
    /**
     * @param  array<int, list<EnrollmentPeriod>>  $periods  Student id => its periods of the year.
     */
    public function __construct(
        private array $periods,
        private int $sectionId,
    ) {}

    public function isCurrent(int $studentId, DateTimeImmutable $today): bool
    {
        return EnrollmentPeriod::currentIn($this->periods[$studentId] ?? [], $this->sectionId, $today);
    }

    public function applies(int $studentId, DateTimeImmutable $date): bool
    {
        return EnrollmentPeriod::applies($this->periods[$studentId] ?? [], $this->sectionId, $date);
    }

    public function notCurrent(int $studentId): GradeNotValid
    {
        return GradeNotValid::notInSection($studentId);
    }
}
