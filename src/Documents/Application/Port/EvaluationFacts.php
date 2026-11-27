<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

/**
 * Report card grades, revisions, pendings, literals and descriptive reports
 * from Evaluation (A-06).
 */
interface EvaluationFacts
{
    /**
     * @param  list<int>  $studentIds
     * @return array<int, array{
     *     level: string,
     *     subjects: list<array{subject: string, term1: ?string, term2: ?string, term3: ?string, termScore: ?string, finalScore: ?string, revision: ?string, finalAfterRevision: ?string, failed: bool}>,
     *     pendings: list<array{subject: string, originGrade: string, originYear: string, standing: string, score: ?int}>,
     *     primaryMarks: array<string, array{literal: ?string, description: ?string, incomplete: bool}>,
     *     initialReports: array<string, array{description: ?string, incomplete: bool}>
     * }>
     */
    public function reportCards(int $schoolYearId, int $sectionId, array $studentIds, ?int $term): array;
}
