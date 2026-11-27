<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\Mark;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use Collator;

/**
 * The literals (Primaria) or the reports (Inicial) of the students of a
 * section (EVA-R04, R05), for its guide, a coordinator of its grade or
 * whoever manages students (EVA-R07.2 – R07.5); anyone else gets not found
 * (EVA-R07.6).
 */
final readonly class MarksSheetQuery
{
    private EvaluationAccess $access;

    public function __construct(
        private StudentMarksRepository $marks,
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
        private EnrollmentFacts $enrollments,
        private EvaluationPermissions $permissions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @throws AuthorizationDenied
     * @throws EvaluationNotFound
     */
    public function __invoke(int $schoolYearId, int $sectionId): MarksSheetView
    {
        $account = $this->access->ensureCanView();
        $section = $this->teaching->section($schoolYearId, $sectionId) ?? throw EvaluationNotFound::create();
        $kind = match ($section->level) {
            MarkKind::Literal->level() => MarkKind::Literal,
            MarkKind::Report->level() => MarkKind::Report,
            default => throw EvaluationNotFound::create(),
        };
        $guides = $this->teaching->guides($account, $sectionId);

        if (! $guides && ! $this->permissions->seesAll() && ! in_array($section->grade, $this->teaching->coordinatedGrades($account), true)) {
            throw EvaluationNotFound::create();
        }

        $canGrade = $guides && $this->permissions->canGrade();
        $roster = $this->enrollments->roster($schoolYearId, $sectionId);
        $collator = new Collator('es');
        usort($roster, fn (RosterStudentFact $a, RosterStudentFact $b) => $collator->compare($a->fullName, $b->fullName));
        $marks = $this->marks->forStudents($schoolYearId, array_map(fn (RosterStudentFact $student) => $student->studentId, $roster));
        $periods = $kind === MarkKind::Literal ? ['1', '2', '3', 'final'] : ['1', '2', '3'];
        $rows = [];

        foreach ($roster as $student) {
            $row = [];

            foreach ($periods as $period) {
                $mark = $marks[$student->studentId]->markOf($period, $kind);
                $row[$period] = $mark === null ? null : self::markData($mark);
            }

            $rows[] = new MarksRow($student->studentId, $student->fullName, $student->identification, $row);
        }

        $editable = [];

        foreach ([1, 2, 3] as $term) {
            $editable[$term] = $canGrade && $this->calendar->gradesEditable($schoolYearId, $term, $this->permissions->canEditClosedTerms());
        }

        $finalOpen = $canGrade && $kind === MarkKind::Literal
            && $this->calendar->termStatus($schoolYearId, 3) === 'closed'
            && $this->calendar->gradesEditable($schoolYearId, 3, true);

        return new MarksSheetView($schoolYearId, $section, $kind->value, $canGrade, $editable, $finalOpen, $rows);
    }

    /**
     * @return array{literal: ?string, description: ?string, incomplete: bool}
     */
    private static function markData(Mark $mark): array
    {
        return ['literal' => $mark->literal?->value, 'description' => $mark->description, 'incomplete' => $mark->incomplete];
    }
}
