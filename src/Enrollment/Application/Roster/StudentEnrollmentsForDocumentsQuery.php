<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\Graduation;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Domain\Enrollment;
use DateTimeImmutable;

/**
 * Cross-module enrollment query for SPEC-012 Documents (A-06): exposes
 * non-annulled enrollments, progression/graduation outcomes (with SPEC-016
 * pending checks) and section eligibility including students withdrawn after
 * a closed term ended (DOC-R01.6, R03.1, R04.5).
 */
final readonly class StudentEnrollmentsForDocumentsQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private StructureFacts $structure,
        private Graduation $graduation = new Graduation,
    ) {}

    /**
     * @return list<DocumentEnrollmentView> Non-annulled enrollments, oldest first.
     */
    public function forStudent(int $studentId): array
    {
        $sectionsByYear = [];
        $views = [];

        foreach ($this->enrollments->historyOf($studentId) as $enrollment) {
            if ($enrollment->annulment() !== null) {
                continue;
            }

            $yearId = $enrollment->schoolYearId();
            $sectionsByYear[$yearId] ??= array_column(
                array_map(fn (SectionFact $section) => [$section->id, $section], $this->structure->sectionsOf($yearId)),
                1,
                0,
            );
            $section = $sectionsByYear[$yearId][$enrollment->placement()->sectionId] ?? null;
            $result = $enrollment->result();
            $graduated = $this->graduation->graduates($enrollment);
            $waitsToGraduate = $this->graduation->waitsToGraduate($enrollment);

            $views[] = new DocumentEnrollmentView(
                enrollmentId: (int) $enrollment->id()?->value,
                schoolYearId: $yearId,
                sectionId: $enrollment->placement()->sectionId,
                gradeCode: $enrollment->placement()->grade,
                gradeName: $section->gradeName ?? $enrollment->placement()->grade,
                level: $section->level ?? $enrollment->placement()->level->value,
                sectionLetter: $section->letter ?? '',
                startsOn: $enrollment->period()->startsOn->format('Y-m-d'),
                endsOn: $enrollment->period()->endsOn?->format('Y-m-d'),
                endReason: $enrollment->period()->endReason?->value,
                isCurrent: $enrollment->isCurrent(),
                outcome: $result?->outcome->value,
                outcomeLabel: $graduated ? 'Egresado' : $result?->outcome->label(),
                waitsToGraduate: $waitsToGraduate,
                graduated: $graduated,
            );
        }

        return $views;
    }

    /**
     * Eligible non-annulled students in a section for a closed term (or final
     * report): students enrolled on or before $termEndDate whose enrollment
     * was still active on $termEndDate (or current). Deduplicated per student
     * preferring the latest enrollment in the section.
     *
     * @return list<DocumentSectionEnrollmentView>
     */
    public function eligibleInSection(int $schoolYearId, int $sectionId, ?string $termEndDate): array
    {
        $cutoff = $termEndDate === null ? null : new DateTimeImmutable($termEndDate);
        $byStudent = [];

        foreach ($this->enrollments->inSection($sectionId) as $enrollment) {
            if ($enrollment->schoolYearId() !== $schoolYearId || $enrollment->annulment() !== null) {
                continue;
            }

            if (! $this->wasEligibleForTerm($enrollment, $cutoff)) {
                continue;
            }

            $result = $enrollment->result();
            $graduated = $this->graduation->graduates($enrollment);

            $byStudent[$enrollment->studentId()] = new DocumentSectionEnrollmentView(
                studentId: $enrollment->studentId(),
                outcome: $result?->outcome->value,
                outcomeLabel: $graduated ? 'Egresado' : $result?->outcome->label(),
                graduated: $graduated,
            );
        }

        return array_values($byStudent);
    }

    private function wasEligibleForTerm(Enrollment $enrollment, ?DateTimeImmutable $cutoff): bool
    {
        if ($enrollment->isCurrent()) {
            return true;
        }

        $endsOn = $enrollment->period()->endsOn;

        if ($cutoff === null || $endsOn === null) {
            return false;
        }

        // Student started before/on term end and remained until at least term end (DOC-R01.6).
        return $enrollment->period()->startsOn <= $cutoff && $endsOn >= $cutoff;
    }
}
