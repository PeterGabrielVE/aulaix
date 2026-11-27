<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * An immutable institution document (report card or certificate) emitted with
 * a non-sequential QR verification code (SPEC-012 DOC-R05 – R07, ADR 0015).
 *
 * Once issued, its snapshot and PDF never mutate; it can only be irreversibly
 * voided with a reason, preserving history.
 */
final class IssuedDocument
{
    private function __construct(
        public readonly ?DocumentId $id,
        public readonly int $studentId,
        public readonly ?int $schoolYearId,
        public readonly ?int $sectionId,
        public readonly string $gradeCode,
        public readonly DocumentKind $kind,
        public readonly ?int $term,
        public readonly string $verificationCodeHash,
        public readonly string $codeHint,
        public readonly string $pdfPath,
        public readonly string $pdfSha256,
        public readonly int $pdfBytes,
        public readonly string $studentFullNameSnapshot,
        public readonly StudentPublicSummary $publicSummary,
        public readonly string $institutionNameSnapshot,
        public readonly ?string $institutionDeaSnapshot,
        public readonly string $periodLabel,
        public readonly int $issuedBy,
        public readonly DateTimeImmutable $issuedAt,
        public readonly ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $voidedAt = null,
        private ?int $voidedBy = null,
        private ?VoidReason $voidReason = null,
    ) {}

    public static function issue(
        int $studentId,
        ?int $schoolYearId,
        ?int $sectionId,
        string $gradeCode,
        DocumentKind $kind,
        ?int $term,
        VerificationCode $verificationCode,
        string $pdfPath,
        string $pdfSha256,
        int $pdfBytes,
        string $studentFullNameSnapshot,
        StudentPublicSummary $publicSummary,
        string $institutionNameSnapshot,
        ?string $institutionDeaSnapshot,
        string $periodLabel,
        int $issuedBy,
        DateTimeImmutable $issuedAt,
        ?ValidityPeriod $validity = null,
    ): self {
        if ($kind === DocumentKind::TermReport) {
            if ($term === null || $term < 1 || $term > 3) {
                throw new InvalidArgumentException('A term report requires a term between 1 and 3.');
            }
        } elseif ($term !== null) {
            throw new InvalidArgumentException("Document kind {$kind->value} cannot carry a term.");
        }

        $expiresAt = null;

        if ($kind->hasExpiration()) {
            $expiresAt = ($validity ?? ValidityPeriod::ofDays(null))->expiresAt($issuedAt);
        } elseif ($validity !== null) {
            throw new InvalidArgumentException("Document kind {$kind->value} does not expire.");
        }

        if ($pdfBytes <= 0) {
            throw new InvalidArgumentException('An issued PDF must have positive byte length.');
        }

        return new self(
            id: null,
            studentId: $studentId,
            schoolYearId: $schoolYearId,
            sectionId: $sectionId,
            gradeCode: $gradeCode,
            kind: $kind,
            term: $term,
            verificationCodeHash: $verificationCode->hash(),
            codeHint: $verificationCode->hint(),
            pdfPath: $pdfPath,
            pdfSha256: $pdfSha256,
            pdfBytes: $pdfBytes,
            studentFullNameSnapshot: $studentFullNameSnapshot,
            publicSummary: $publicSummary,
            institutionNameSnapshot: $institutionNameSnapshot,
            institutionDeaSnapshot: $institutionDeaSnapshot,
            periodLabel: $periodLabel,
            issuedBy: $issuedBy,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
        );
    }

    public static function reconstitute(
        DocumentId $id,
        int $studentId,
        ?int $schoolYearId,
        ?int $sectionId,
        string $gradeCode,
        DocumentKind $kind,
        ?int $term,
        string $verificationCodeHash,
        string $codeHint,
        string $pdfPath,
        string $pdfSha256,
        int $pdfBytes,
        string $studentFullNameSnapshot,
        StudentPublicSummary $publicSummary,
        string $institutionNameSnapshot,
        ?string $institutionDeaSnapshot,
        string $periodLabel,
        int $issuedBy,
        DateTimeImmutable $issuedAt,
        ?DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $voidedAt,
        ?int $voidedBy,
        ?string $voidReason,
    ): self {
        return new self(
            id: $id,
            studentId: $studentId,
            schoolYearId: $schoolYearId,
            sectionId: $sectionId,
            gradeCode: $gradeCode,
            kind: $kind,
            term: $term,
            verificationCodeHash: $verificationCodeHash,
            codeHint: $codeHint,
            pdfPath: $pdfPath,
            pdfSha256: $pdfSha256,
            pdfBytes: $pdfBytes,
            studentFullNameSnapshot: $studentFullNameSnapshot,
            publicSummary: $publicSummary,
            institutionNameSnapshot: $institutionNameSnapshot,
            institutionDeaSnapshot: $institutionDeaSnapshot,
            periodLabel: $periodLabel,
            issuedBy: $issuedBy,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            voidedAt: $voidedAt,
            voidedBy: $voidedBy,
            voidReason: $voidReason === null ? null : VoidReason::fromString($voidReason),
        );
    }

    /**
     * Irreversibly voids the document (DOC-R06.1 – R06.3).
     *
     * @throws BusinessRuleViolation
     */
    public function void(VoidReason $reason, int $actorId, DateTimeImmutable $at): void
    {
        if ($this->voidedAt !== null) {
            throw BusinessRuleViolation::onField('document', 'Ese documento ya fue anulado.');
        }

        $this->voidedAt = $at;
        $this->voidedBy = $actorId;
        $this->voidReason = $reason;
    }

    public function statusAt(DateTimeImmutable $now): DocumentStatus
    {
        if ($this->voidedAt !== null) {
            return DocumentStatus::Voided;
        }

        if ($this->expiresAt !== null && $now > $this->expiresAt) {
            return DocumentStatus::Expired;
        }

        return DocumentStatus::Valid;
    }

    public function isVoided(): bool
    {
        return $this->voidedAt !== null;
    }

    public function isDownloadable(): bool
    {
        return ! $this->isVoided();
    }

    public function voidedAt(): ?DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function voidedBy(): ?int
    {
        return $this->voidedBy;
    }

    public function voidReason(): ?VoidReason
    {
        return $this->voidReason;
    }
}
