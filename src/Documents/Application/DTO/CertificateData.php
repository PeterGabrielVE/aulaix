<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\DTO;

use AulaX\Documents\Domain\DocumentKind;

/**
 * Data transfer object for rendering any of the five institution certificates
 * (SPEC-012 DOC-R04.1 – R04.6).
 */
final readonly class CertificateData
{
    public function __construct(
        public InstitutionHeaderData $header,
        public DocumentKind $kind,
        public string $studentFullName,
        public string $studentIdentification,
        public string $schoolYearName,
        public string $gradeName,
        public ?string $sectionLetter,
        public string $issuedAtDisplay,
        public ?string $expiresAtDisplay,
        public string $verificationCodeFormatted,
        public string $verificationUrl,
        public string $qrDataUri,
        public ?string $enrollmentDateDisplay = null,
        public ?string $withdrawalDateDisplay = null,
        public ?string $withdrawalReason = null,
        public ?string $goodConductNote = null,
        public ?string $progressionResultLabel = null,
    ) {}
}
