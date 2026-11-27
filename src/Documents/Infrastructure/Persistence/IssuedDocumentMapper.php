<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Persistence;

use AulaX\Documents\Domain\DocumentId;
use AulaX\Documents\Domain\DocumentKind;
use AulaX\Documents\Domain\IssuedDocument;
use AulaX\Documents\Domain\StudentPublicSummary;
use DateTimeImmutable;

final class IssuedDocumentMapper
{
    public static function toDomain(IssuedDocumentModel $model): IssuedDocument
    {
        return IssuedDocument::reconstitute(
            id: DocumentId::fromInt((int) $model->id),
            studentId: (int) $model->student_id,
            schoolYearId: $model->school_year_id === null ? null : (int) $model->school_year_id,
            sectionId: $model->section_id === null ? null : (int) $model->section_id,
            gradeCode: (string) $model->grade_code,
            kind: DocumentKind::from((string) $model->kind),
            term: $model->term === null ? null : (int) $model->term,
            verificationCodeHash: (string) $model->verification_code_hash,
            codeHint: (string) $model->code_hint,
            pdfPath: (string) $model->pdf_path,
            pdfSha256: (string) $model->pdf_sha256,
            pdfBytes: (int) $model->pdf_bytes,
            studentFullNameSnapshot: (string) $model->student_full_name_snapshot,
            publicSummary: StudentPublicSummary::reconstitute((string) $model->student_initials, (string) $model->student_id_last4),
            institutionNameSnapshot: (string) $model->institution_name_snapshot,
            institutionDeaSnapshot: $model->institution_dea_snapshot,
            periodLabel: (string) $model->period_label,
            issuedBy: (int) $model->issued_by,
            issuedAt: new DateTimeImmutable($model->issued_at->format('Y-m-d H:i:s')),
            expiresAt: $model->expires_at === null ? null : new DateTimeImmutable($model->expires_at->format('Y-m-d H:i:s')),
            voidedAt: $model->voided_at === null ? null : new DateTimeImmutable($model->voided_at->format('Y-m-d H:i:s')),
            voidedBy: $model->voided_by === null ? null : (int) $model->voided_by,
            voidReason: $model->void_reason,
        );
    }
}
