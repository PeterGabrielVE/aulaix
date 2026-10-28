<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Hooks;

use AulaX\Enrollment\Application\Port\OutcomeProposer;
use AulaX\Enrollment\Application\Port\OutcomeSuggestion;
use AulaX\Evaluation\Application\Gradebook\StudentScores;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\OutcomeProposal;
use AulaX\Evaluation\Domain\StudentMarksRepository;

/**
 * The result of the year proposed from the grades, for the screen
 * «Prosecución» of SPEC-010 (EVA-R08): in Media General by the failed final
 * grades, in Primaria by the final literal, in Inicial always promoted.
 * Whoever asks already checked who may see the section.
 */
final readonly class GradeOutcomeProposer implements OutcomeProposer
{
    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private StudentMarksRepository $marks,
        private TeachingFacts $teaching,
        private EnrollmentFacts $enrollments,
    ) {}

    public function propose(int $schoolYearId, int $sectionId, array $students): array
    {
        $section = $this->teaching->section($schoolYearId, $sectionId);

        if ($section === null || $students === []) {
            return [];
        }

        $proposals = match ($section->level) {
            'media_general' => $this->secondary($schoolYearId, $section->grade, $section->level, $students),
            MarkKind::Literal->level() => $this->primary($schoolYearId, $students),
            default => array_map(fn () => OutcomeProposal::forInitial(), $students),
        };

        return array_map(fn (array $proposal) => new OutcomeSuggestion($proposal['outcome'], $proposal['pendingSubjects'], $proposal['missing']), $proposals);
    }

    /**
     * @param  array<int, int>  $students  Enrollment id => student id.
     * @return array<int, array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>}>
     */
    private function secondary(int $schoolYearId, string $grade, string $level, array $students): array
    {
        $names = $this->teaching->subjectNames($level);
        $periods = $this->enrollments->periods($schoolYearId, array_values($students));
        $scores = [];

        foreach ($this->teaching->curriculum($schoolYearId, $grade) as $subjectId) {
            $scores[$subjectId] = new StudentScores($this->plans, $this->grades, $schoolYearId, $subjectId, $grade);
        }

        return array_map(fn (int $studentId) => OutcomeProposal::forSecondary(
            array_map(fn (StudentScores $subject) => $subject->final($studentId, $periods[$studentId] ?? []), $scores),
            $names,
        ), $students);
    }

    /**
     * @param  array<int, int>  $students  Enrollment id => student id.
     * @return array<int, array{outcome: ?string, pendingSubjects: list<int>, missing: list<string>}>
     */
    private function primary(int $schoolYearId, array $students): array
    {
        $marks = $this->marks->forStudents($schoolYearId, array_values($students));

        return array_map(fn (int $studentId) => OutcomeProposal::forPrimary($marks[$studentId]->markOf('final', MarkKind::Literal)?->literal), $students);
    }
}
