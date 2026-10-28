<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\NextYear;

use AulaX\AcademicStructure\Application\Grades\GradeView;
use AulaX\Enrollment\Application\AgeAdmission;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Application\Roster\Overviews;
use AulaX\Enrollment\Domain\AgeOutOfRange;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\PromotionRules;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Application\Enrollable\EnrollableStudent;

/**
 * Who of a school year goes on to the next one and where (MAT-R07): the
 * grade each result leads to, and why the rest are left out. The proposal
 * and its enrollment share it, so the enrollment checks it again under
 * the calendar lock.
 */
final readonly class NextYearPlanner
{
    private AgeAdmission $ages;

    public function __construct(
        private EnrollmentList $list,
        private StudentFacts $students,
        private StructureFacts $structure,
        private CalendarFacts $calendar,
        AgeRules $ageRules,
        EnrollmentPermissions $permissions,
    ) {
        $this->ages = new AgeAdmission($ageRules, $calendar, $permissions);
    }

    /**
     * @throws BusinessRuleViolation When there is no next year, or it is closed (MAT-R07.6).
     */
    public function plan(int $fromSchoolYearId): NextYearProposal
    {
        $next = $this->calendar->nextOf($fromSchoolYearId) ?? throw BusinessRuleViolation::onField('school_year', 'No hay un año escolar siguiente.');

        if ($this->calendar->statusOf($next) === 'closed') {
            throw BusinessRuleViolation::onField('school_year', 'El año escolar siguiente está cerrado.');
        }

        $grades = $this->structure->grades();
        $gradeNames = array_column(array_map(fn (GradeView $grade) => [$grade->code, $grade->name], $grades), 1, 0);
        $order = array_map(fn (GradeView $grade) => $grade->code, $grades);
        $sections = $this->structure->sectionsOf($next);
        $counts = $this->list->currentCountsOf($next);
        $current = $this->list->currentInYear($fromSchoolYearId);
        $students = $this->students->byIds(array_map(fn (Enrollment $enrollment) => $enrollment->studentId(), $current));
        $alreadyThere = array_flip($this->list->currentStudentsOf($next));
        $entries = [];
        $excluded = [];

        foreach ($current as $enrollment) {
            $student = $students[$enrollment->studentId()] ?? null;

            if ($student === null) {
                continue;
            }

            $toGrade = $enrollment->result() === null ? null : PromotionRules::nextGrade($enrollment->placement()->grade, $enrollment->result()->outcome, $order);
            $options = array_values(array_filter($sections, fn (SectionFact $section) => $section->grade === $toGrade));
            $reason = match (true) {
                ! $student->active => 'Está retirado.',
                $enrollment->result() === null => 'No tiene resultado de prosecución.',
                $enrollment->graduates() => 'Egresó.',
                $enrollment->waitsToGraduate() => 'Está pendiente de egresar.',
                isset($alreadyThere[$student->id]) => 'Ya está inscrito en el año siguiente.',
                $toGrade === null || $options === [] => 'El plantel no tiene secciones de '.($gradeNames[$toGrade] ?? 'ese grado').' el año siguiente.',
                default => $this->outOfAgeRange($student, $options[0]),
            };

            if ($reason !== null) {
                $excluded[] = new ExcludedStudent($student->id, $student->fullName, $reason);

                continue;
            }

            $entries[] = new ProposalEntry(
                $student->id,
                $student->fullName,
                $enrollment->result()?->outcome->value ?? '',
                (string) $toGrade,
                $gradeNames[$toGrade] ?? (string) $toGrade,
                array_map(fn (SectionFact $section) => $section->id, $options),
            );
        }

        return new NextYearProposal(
            $fromSchoolYearId,
            $next,
            Overviews::byName($entries, fn (ProposalEntry $entry) => $entry->fullName),
            Overviews::byName($excluded, fn (ExcludedStudent $entry) => $entry->fullName),
            array_map(fn (SectionFact $section) => Overviews::section($section, $counts[$section->id] ?? 0), $sections),
            array_column(array_map(fn (SectionFact $section) => [$section->id, $section], $sections), 1, 0),
        );
    }

    /**
     * Out of the age range, a student is enrolled one by one, with a
     * directive's exception (MAT-R07.7).
     */
    private function outOfAgeRange(EnrollableStudent $student, SectionFact $section): ?string
    {
        try {
            $this->ages->admit($student, $section, null, 0);

            return null;
        } catch (AgeOutOfRange) {
            return "Su edad está fuera del rango de {$section->gradeName}: inscríbelo uno a uno con la excepción.";
        }
    }
}
