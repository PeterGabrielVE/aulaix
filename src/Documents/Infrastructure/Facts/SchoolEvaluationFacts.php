<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Facts;

use AulaX\Documents\Application\Port\EvaluationFacts;
use AulaX\Evaluation\Application\ReportCard\ReportCardSnapshot;
use AulaX\Evaluation\Application\ReportCard\StudentReportCardQuery;
use AulaX\Evaluation\Application\ReportCard\SubjectReportRow;
use AulaX\Evaluation\Application\StudentPendings\StudentPendingView;

/**
 * Reads report card grades, revisions, pendings, literals and descriptive
 * reports through Evaluation's public Application query (A-06).
 */
final readonly class SchoolEvaluationFacts implements EvaluationFacts
{
    public function __construct(private StudentReportCardQuery $reportCards) {}

    public function reportCards(int $schoolYearId, int $sectionId, array $studentIds, ?int $term): array
    {
        $snapshots = $this->reportCards->forStudents($schoolYearId, $sectionId, $studentIds, $term);
        $result = [];

        foreach ($snapshots as $studentId => $snapshot) {
            $result[$studentId] = self::mapSnapshot($snapshot);
        }

        return $result;
    }

    /**
     * @return array{
     *     level: string,
     *     subjects: list<array{subject: string, term1: ?string, term2: ?string, term3: ?string, termScore: ?string, finalScore: ?string, revision: ?string, finalAfterRevision: ?string, failed: bool}>,
     *     pendings: list<array{subject: string, originGrade: string, originYear: string, standing: string, score: ?int}>,
     *     primaryMarks: array<string, array{literal: ?string, description: ?string, incomplete: bool}>,
     *     initialReports: array<string, array{description: ?string, incomplete: bool}>
     * }
     */
    private static function mapSnapshot(ReportCardSnapshot $snapshot): array
    {
        return [
            'level' => $snapshot->level,
            'subjects' => array_map(fn (SubjectReportRow $row) => [
                'subject' => $row->subjectName,
                'term1' => $row->term1,
                'term2' => $row->term2,
                'term3' => $row->term3,
                'termScore' => $row->termScore,
                'finalScore' => $row->finalScore,
                'revision' => $row->revision,
                'finalAfterRevision' => $row->finalAfterRevision,
                'failed' => $row->failed,
            ], $snapshot->subjects),
            'pendings' => array_map(fn (StudentPendingView $pending) => [
                'subject' => $pending->subjectName,
                'originGrade' => $pending->originGradeName,
                'originYear' => $pending->originYearName,
                'standing' => match ($pending->standing) {
                    'approved' => 'Aprobada',
                    'failed' => 'Reprobada',
                    default => 'Cursando',
                },
                'score' => $pending->score,
            ], $snapshot->pendings),
            'primaryMarks' => $snapshot->primaryMarks,
            'initialReports' => $snapshot->initialReports,
        ];
    }
}
