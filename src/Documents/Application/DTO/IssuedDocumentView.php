<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

/**
 * Read-only row for the internal document history views (DOC-R05.5, A-08).
 */
final readonly class IssuedDocumentView
{
    public function __construct(
        public int $id,
        public int $studentId,
        public string $studentFullName,
        public string $gradeCode,
        public string $kind,
        public string $kindLabel,
        public ?int $schoolYearId,
        public ?int $term,
        public string $periodLabel,
        public string $codeHint,
        public string $status,
        public string $statusLabel,
        public bool $downloadable,
        public string $issuedAt,
        public string $issuedByName,
        public ?string $expiresAt,
        public ?string $voidedAt,
        public ?string $voidedByName,
        public ?string $voidReason,
    ) {}
}
