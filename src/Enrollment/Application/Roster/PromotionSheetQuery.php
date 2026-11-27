<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\Catalogs\Application\Subjects;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\OutcomeProposers;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\Level;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\PromotionRules;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The results of a section's students: who has one and who does not
 * (MAT-R06.10), with the results its level allows and the subjects of its
 * curriculum to choose the pending ones from.
 */
final readonly class PromotionSheetQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private StudentFacts $students,
        private StructureFacts $structure,
        private CalendarFacts $calendar,
        private Subjects $subjects,
        private EnrollmentPermissions $permissions,
        private OutcomeProposers $proposers = new OutcomeProposers([]),
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws SchoolYearNotFound
     * @throws SectionNotFound
     */
    public function __invoke(int $schoolYearId, int $sectionId): PromotionSheet
    {
        EnrollmentAccess::ensureCanManage($this->permissions);
        $matches = array_values(array_filter($this->structure->sectionsOf($schoolYearId), fn (SectionFact $section) => $section->id === $sectionId));
        $section = $matches[0] ?? throw SectionNotFound::create();
        $enrollments = $this->enrollments->currentIn($sectionId);
        $students = $this->students->byIds(array_map(fn (Enrollment $enrollment) => $enrollment->studentId(), $enrollments));
        $proposals = $this->proposers->propose($schoolYearId, $sectionId, array_combine(
            array_map(fn (Enrollment $enrollment) => (int) $enrollment->id()?->value, $enrollments),
            array_map(fn (Enrollment $enrollment) => $enrollment->studentId(), $enrollments),
        ));
        $rows = [];

        foreach ($enrollments as $enrollment) {
            $student = $students[$enrollment->studentId()] ?? null;

            if ($student !== null) {
                $rows[] = new PromotionRow(
                    (int) $enrollment->id()?->value,
                    $student->fullName,
                    $student->identification,
                    $enrollment->result()?->outcome->value,
                    $enrollment->result()?->pendingSubjects ?? [],
                    $proposals[(int) $enrollment->id()?->value] ?? null,
                );
            }
        }

        $curriculum = $this->structure->curriculum($schoolYearId, $section->grade);
        $subjects = array_values(array_filter(
            $this->subjects->ofLevel($section->level),
            fn (array $subject) => in_array($subject['id'], $curriculum, true),
        ));

        return new PromotionSheet(
            $schoolYearId,
            Overviews::section($section, count($enrollments)),
            $this->calendar->statusOf($schoolYearId) === 'active' && $this->calendar->lastTermClosed($schoolYearId),
            Overviews::byName($rows, fn (PromotionRow $row) => $row->fullName),
            array_map(fn (Outcome $outcome) => $outcome->value, PromotionRules::allowedOutcomes(Level::from($section->level))),
            array_map(fn (array $subject) => ['id' => $subject['id'], 'name' => $subject['name']], $subjects),
        );
    }
}
