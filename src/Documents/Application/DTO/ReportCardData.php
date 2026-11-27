<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

/**
 * Complete, pre-escaped data transfer object for rendering a single term or
 * final report card PDF across Educación Inicial, Primaria and Media General
 * (SPEC-012 DOC-R01, R02, R03).
 */
final readonly class ReportCardData
{
    /**
     * @param  list<array{subject: string, term1: ?string, term2: ?string, term3: ?string, termScore: ?string, finalScore: ?string, revision: ?string, finalAfterRevision: ?string, failed: bool}>  $subjects
     * @param  list<array{subject: string, originGrade: string, originYear: string, standing: string, score: ?int}>  $pendings
     * @param  array<string, array{literal: ?string, description: ?string, incomplete: bool}>  $primaryMarks
     * @param  array<string, array{description: ?string, incomplete: bool}>  $initialReports
     */
    public function __construct(
        public InstitutionHeaderData $header,
        public string $title,
        public string $studentFullName,
        public string $studentIdentification,
        public string $schoolYearName,
        public string $gradeName,
        public string $sectionLetter,
        public string $level,
        public ?int $term,
        public bool $isFinal,
        public string $periodLabel,
        public string $issuedAtDisplay,
        public string $verificationCodeFormatted,
        public string $verificationUrl,
        public string $qrDataUri,
        public array $subjects = [],
        public array $pendings = [],
        public array $primaryMarks = [],
        public array $initialReports = [],
        public ?string $progressionLabel = null,
    ) {}
}
