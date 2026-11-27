<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Hooks;

use AulaX\Enrollment\Application\Port\OutcomeProposer;
use AulaX\Enrollment\Application\Port\OutcomeSuggestion;
use AulaX\Evaluation\Application\Gradebook\StudentScores;
use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\FinalAfterRevision;
use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\OutcomeProposal;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\RevisionRepository;
use AulaX\Evaluation\Domain\StudentMarksRepository;

/**
 * The result of the year proposed from the grades, for the screen
 * «Prosecución» of SPEC-010 (EVA-R08): in Media General by the final grades
 * after their revision (RVP-R02.3) and the pending subjects owed this year
 * (RVP-R05.3, R05.4), in Primaria by the final literal, in Inicial always
 * promoted.
 * Whoever asks already checked who may see the section.
 */
final readonly class GradeOutcomeProposer implements OutcomeProposer
{
    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private StudentMarksRepository $marks,
        private RevisionRepository $revisions,
        private TeachingFacts $teaching,
        private EnrollmentFacts $enrollments,
        private PendingMembers $members,
        private CalendarFacts $calendar,
    ) {}

    public function propose(int $schoolYearId, int $sectionId, array $students): array
    {
        $section = $this->teaching->section($schoolYearId, $sectionId);

        if ($section === null || $students === []) {
            return [];
        }

        $proposals = match ($section->level) {
            'media_general' => $this->secondary($schoolYearId, $sectionId, $section->grade, $section->level, $students),
            MarkKind::Literal->level() => $this->primary($schoolYearId, $students),
            default => array_map(fn () => OutcomeProposal::forInitial(), $students),
        };

        return array_map(fn (array $proposal) => new OutcomeSuggestion($proposal['outcome'], $proposal['pendingSubjects'], $proposal['missing'], $proposal['reasons']), $proposals);
    }

    /**
     * @param  array<int, int>  $students  Enrollment id => student id.
     * @return array<int, array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>, reasons: list<string>}>
     */
    private function secondary(int $schoolYearId, int $sectionId, string $grade, string $level, array $students): array
    {
        $names = $this->teaching->subjectNames($level);
        $studentIds = array_values($students);
        $periods = $this->enrollments->periods($schoolYearId, $studentIds);
        $scores = [];
        $revisions = [];

        foreach ($this->teaching->curriculum($schoolYearId, $grade) as $subjectId) {
            $scores[$subjectId] = new StudentScores($this->plans, $this->grades, $schoolYearId, $subjectId, $grade);
            $revisions[$subjectId] = $this->revisions->revisionsOf($schoolYearId, $subjectId, PlanOwner::section($sectionId), $studentIds);
        }

        $pendings = $this->pendings($schoolYearId, $studentIds, $names);
        $thirdTermClosed = $this->calendar->termStatus($schoolYearId, 3) === 'closed';

        return array_map(fn (int $studentId) => OutcomeProposal::forSecondary(
            array_map(fn (int $subjectId) => FinalAfterRevision::of(
                $scores[$subjectId]->final($studentId, $periods[$studentId] ?? []),
                $revisions[$subjectId][$studentId]->mark(),
            ), array_combine(array_keys($scores), array_keys($scores))),
            $names,
            $pendings[$studentId] ?? [],
            $thirdTermClosed,
        ), $students);
    }

    /**
     * The pending subjects each student owes this year, by name («Física de
     * 2.º año»), with the final grade after revision of its group. A group
     * without teacher has no plan yet: its subject is still studied.
     *
     * @param  list<int>  $studentIds
     * @param  array<int, string>  $names
     * @return array<int, array<string, FinalAfterRevision>>
     */
    private function pendings(int $schoolYearId, array $studentIds, array $names): array
    {
        $grades = null;
        $pendings = [];

        foreach ($this->members->of($schoolYearId) as $key => $members) {
            $owing = array_intersect($members, $studentIds);

            if ($owing === []) {
                continue;
            }

            [$subjectId, $originGrade] = explode(':', $key);
            $grades ??= $this->teaching->gradeNames();
            $name = ($names[(int) $subjectId] ?? 'una materia').' de '.($grades[$originGrade] ?? $originGrade);
            $group = $this->members->group($schoolYearId, (int) $subjectId, $originGrade);
            $scores = $group === null ? null : $this->members->scoresOf($group);
            $scores?->load(array_values($owing));

            foreach ($owing as $studentId) {
                $pendings[$studentId][$name] = $scores?->result($studentId) ?? FinalAfterRevision::of(FinalScore::of([]), null);
            }
        }

        return $pendings;
    }

    /**
     * @param  array<int, int>  $students  Enrollment id => student id.
     * @return array<int, array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>, reasons: list<string>}>
     */
    private function primary(int $schoolYearId, array $students): array
    {
        $marks = $this->marks->forStudents($schoolYearId, array_values($students));

        return array_map(fn (int $studentId) => OutcomeProposal::forPrimary($marks[$studentId]->markOf('final', MarkKind::Literal)?->literal), $students);
    }
}
