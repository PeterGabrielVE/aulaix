<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Persistence;

use AulaX\Documents\Domain\DocumentId;
use AulaX\Documents\Domain\IssuedDocument;
use AulaX\Documents\Domain\IssuedDocumentRepository;

/**
 * Eloquent repository for issued documents; PostgreSQL RLS scopes every read
 * and write to the open institution (S-01).
 */
final class EloquentIssuedDocumentRepository implements IssuedDocumentRepository
{
    public function save(IssuedDocument $document): DocumentId
    {
        if ($document->id === null) {
            $model = IssuedDocumentModel::query()->create([
                'student_id' => $document->studentId,
                'school_year_id' => $document->schoolYearId,
                'section_id' => $document->sectionId,
                'grade_code' => $document->gradeCode,
                'kind' => $document->kind->value,
                'term' => $document->term,
                'verification_code_hash' => $document->verificationCodeHash,
                'code_hint' => $document->codeHint,
                'pdf_path' => $document->pdfPath,
                'pdf_sha256' => $document->pdfSha256,
                'pdf_bytes' => $document->pdfBytes,
                'student_full_name_snapshot' => $document->studentFullNameSnapshot,
                'student_initials' => $document->publicSummary->initials,
                'student_id_last4' => $document->publicSummary->idLast4,
                'institution_name_snapshot' => $document->institutionNameSnapshot,
                'institution_dea_snapshot' => $document->institutionDeaSnapshot,
                'period_label' => $document->periodLabel,
                'issued_by' => $document->issuedBy,
                'issued_at' => $document->issuedAt->format('Y-m-d H:i:s'),
                'expires_at' => $document->expiresAt?->format('Y-m-d H:i:s'),
                'voided_at' => $document->voidedAt()?->format('Y-m-d H:i:s'),
                'voided_by' => $document->voidedBy(),
                'void_reason' => $document->voidReason()?->value,
            ]);

            return DocumentId::fromInt((int) $model->id);
        }

        // Only voiding fields may ever change after issuance (DOC-R05.4, R06.1).
        IssuedDocumentModel::query()
            ->whereKey($document->id->value)
            ->update([
                'voided_at' => $document->voidedAt()?->format('Y-m-d H:i:s'),
                'voided_by' => $document->voidedBy(),
                'void_reason' => $document->voidReason()?->value,
                'updated_at' => now(),
            ]);

        return $document->id;
    }

    public function byId(DocumentId $id): ?IssuedDocument
    {
        $model = IssuedDocumentModel::query()->find($id->value);

        return $model === null ? null : IssuedDocumentMapper::toDomain($model);
    }

    public function byVerificationCodeHash(string $hash): ?IssuedDocument
    {
        $model = IssuedDocumentModel::query()
            ->where('verification_code_hash', $hash)
            ->first();

        return $model === null ? null : IssuedDocumentMapper::toDomain($model);
    }
}
