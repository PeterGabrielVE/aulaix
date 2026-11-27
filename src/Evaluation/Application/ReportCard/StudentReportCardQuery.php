<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\ReportCard;

use AulaX\Evaluation\Application\Gradebook\StudentScores;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Application\StudentPendings\StudentPendingsQuery;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\FinalAfterRevision;
use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\GradeChange;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\RevisionRepository;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use AulaX\Evaluation\Domain\TermResult;

/**
 * Public cross-module query for SPEC-012 Documents (A-06, DOC-R01, R02, R03):
 * builds term or final report-card snapshots for one or many students in a
 * section without N+1 queries. Caller enforces `emitir-documentos` and scope.
 */
final readonly class StudentReportCardQuery
{
    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private StudentMarksRepository $marks,
        private RevisionRepository $revisions,
        private TeachingFacts $teaching,
        private EnrollmentFacts $enrollments,
        private StudentPendingsQuery $pendings,
    ) {}

    /**
     * @param  list<int>  $studentIds
     * @return array<int, ReportCardSnapshot> Student id => snapshot.
     */
    public function forStudents(int $schoolYearId, int $sectionId, array $studentIds, ?int $term): array
    {
        if ($studentIds === []) {
            return [];
        }

        $section = $this->teaching->section($schoolYearId, $sectionId);

        if ($section === null) {
            return [];
        }

        if ($section->level === 'media_general') {
            return $this->mediaGeneral($schoolYearId, $sectionId, $section->grade, $studentIds, $term);
        }

        if ($section->level === MarkKind::Literal->level()) {
            return $this->primaria($schoolYearId, $studentIds);
        }

        return $this->inicial($schoolYearId, $studentIds);
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, ReportCardSnapshot>
     */
    private function mediaGeneral(int $schoolYearId, int $sectionId, string $grade, array $studentIds, ?int $term): array
    {
        $curriculum = $this->teaching->curriculum($schoolYearId, $grade);
        $subjectNames = $this->teaching->subjectNames('media_general');
        $periods = $this->enrollments->periods($schoolYearId, $studentIds);

        $scoresBySubject = [];
        $revisionsBySubject = [];

        foreach ($curriculum as $subjectId) {
            $scoresBySubject[$subjectId] = new StudentScores($this->plans, $this->grades, $schoolYearId, $subjectId, $grade);
            if ($term === null) {
                $revisionsBySubject[$subjectId] = $this->revisions->revisionsOf($schoolYearId, $subjectId, PlanOwner::section($sectionId), $studentIds);
            }
        }

        $snapshots = [];

        foreach ($studentIds as $studentId) {
            $studentPeriods = $periods[$studentId] ?? [];
            $rows = [];

            foreach ($curriculum as $subjectId) {
                $scores = $scoresBySubject[$subjectId];
                $t1 = $scores->term(1, $studentId, $studentPeriods);
                $t2 = $scores->term(2, $studentId, $studentPeriods);
                $t3 = $scores->term(3, $studentId, $studentPeriods);

                if ($term !== null) {
                    $selected = match ($term) {
                        1 => $t1,
                        2 => $t2,
                        default => $t3,
                    };

                    $rows[] = new SubjectReportRow(
                        subjectId: $subjectId,
                        subjectName: $subjectNames[$subjectId] ?? '',
                        term1: self::formatTerm($t1),
                        term2: self::formatTerm($t2),
                        term3: self::formatTerm($t3),
                        termScore: self::formatTerm($selected),
                        finalScore: null,
                        revision: null,
                        finalAfterRevision: null,
                        failed: $selected->score !== null && $selected->score < 10,
                    );

                    continue;
                }

                $final = $scores->final($studentId, $studentPeriods);
                $revisionMark = ($revisionsBySubject[$subjectId][$studentId] ?? null)?->mark();
                $afterRevision = FinalAfterRevision::of($final, $revisionMark);
                $failed = $afterRevision->failed() || $afterRevision->state === FinalAfterRevision::NEEDS_REVISION;

                $rows[] = new SubjectReportRow(
                    subjectId: $subjectId,
                    subjectName: $subjectNames[$subjectId] ?? '',
                    term1: self::formatTerm($t1),
                    term2: self::formatTerm($t2),
                    term3: self::formatTerm($t3),
                    termScore: null,
                    finalScore: self::formatFinal($final),
                    revision: GradeChange::display($revisionMark),
                    finalAfterRevision: $afterRevision->score !== null ? (string) $afterRevision->score : self::formatFinal($final),
                    failed: $failed,
                );
            }

            $pendings = $term === null ? ($this->pendings)($studentId) : [];

            $snapshots[$studentId] = new ReportCardSnapshot(
                studentId: $studentId,
                level: 'media_general',
                subjects: $rows,
                pendings: $pendings,
            );
        }

        return $snapshots;
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, ReportCardSnapshot>
     */
    private function primaria(int $schoolYearId, array $studentIds): array
    {
        $allMarks = $this->marks->forStudents($schoolYearId, $studentIds);
        $snapshots = [];

        foreach ($studentIds as $studentId) {
            $studentMarks = $allMarks[$studentId] ?? null;
            $primary = [];

            foreach (['1', '2', '3', 'final'] as $period) {
                $mark = $studentMarks?->markOf($period, MarkKind::Literal);

                if ($mark !== null) {
                    $primary[$period] = [
                        'literal' => $mark->literal?->value,
                        'description' => $mark->description,
                        'incomplete' => $mark->incomplete,
                    ];
                }
            }

            $snapshots[$studentId] = new ReportCardSnapshot(
                studentId: $studentId,
                level: MarkKind::Literal->level(),
                primaryMarks: $primary,
            );
        }

        return $snapshots;
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, ReportCardSnapshot>
     */
    private function inicial(int $schoolYearId, array $studentIds): array
    {
        $allMarks = $this->marks->forStudents($schoolYearId, $studentIds);
        $snapshots = [];

        foreach ($studentIds as $studentId) {
            $studentMarks = $allMarks[$studentId] ?? null;
            $reports = [];

            foreach (['1', '2', '3'] as $period) {
                $mark = $studentMarks?->markOf($period, MarkKind::Report);

                if ($mark !== null) {
                    $reports[$period] = [
                        'description' => $mark->description,
                        'incomplete' => $mark->incomplete,
                    ];
                }
            }

            $snapshots[$studentId] = new ReportCardSnapshot(
                studentId: $studentId,
                level: MarkKind::Report->level(),
                initialReports: $reports,
            );
        }

        return $snapshots;
    }

    private static function formatTerm(TermResult $result): string
    {
        return match ($result->state) {
            'scored' => (string) $result->score,
            'not_applicable' => 'No aplica',
            default => 'Incompleta',
        };
    }

    private static function formatFinal(FinalScore $final): string
    {
        return match ($final->state) {
            'scored' => (string) $final->score,
            'not_applicable' => 'No aplica',
            default => 'Incompleta',
        };
    }
}
