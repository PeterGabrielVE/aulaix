<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Persistence;

use AulaX\Documents\Application\DTO\DocumentHistoryFilters;
use AulaX\Documents\Application\DTO\DocumentHistoryPage;
use AulaX\Documents\Application\DTO\IssuedDocumentView;
use AulaX\Documents\Application\Port\DocumentHistoryDirectory;
use AulaX\Documents\Domain\DocumentKind;
use AulaX\Documents\Domain\DocumentStatus;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * CQRS read adapter for the document history tables (A-08, DOC-R05.5).
 * Escapes LIKE wildcards (S-09) and runs under PostgreSQL RLS (S-01).
 */
final class EloquentDocumentHistoryDirectory implements DocumentHistoryDirectory
{
    public function paginate(DocumentHistoryFilters $filters, ?array $allowedGrades, DateTimeImmutable $now): DocumentHistoryPage
    {
        if ($allowedGrades !== null && $allowedGrades === []) {
            return new DocumentHistoryPage([], 0, 1, $filters->perPage, 1);
        }

        $query = $this->baseQuery($allowedGrades);

        if ($filters->kind !== null && $filters->kind !== '') {
            $query->where('d.kind', $filters->kind);
        }

        if ($filters->schoolYearId !== null) {
            $query->where('d.school_year_id', $filters->schoolYearId);
        }

        $nowSql = $now->format('Y-m-d H:i:s');

        if ($filters->status === DocumentStatus::Voided->value) {
            $query->whereNotNull('d.voided_at');
        } elseif ($filters->status === DocumentStatus::Expired->value) {
            $query->whereNull('d.voided_at')
                ->whereNotNull('d.expires_at')
                ->where('d.expires_at', '<', $nowSql);
        } elseif ($filters->status === DocumentStatus::Valid->value) {
            $query->whereNull('d.voided_at')
                ->where(fn (Builder $sub) => $sub->whereNull('d.expires_at')->orWhere('d.expires_at', '>=', $nowSql));
        }

        if ($filters->search !== null && trim($filters->search) !== '') {
            $escaped = addcslashes(trim($filters->search), '%_\\');
            $query->where(function (Builder $sub) use ($escaped) {
                $sub->where('d.student_full_name_snapshot', 'ilike', "%{$escaped}%")
                    ->orWhere('d.code_hint', 'ilike', "%{$escaped}%")
                    ->orWhere('d.period_label', 'ilike', "%{$escaped}%");
            });
        }

        $total = (clone $query)->count();
        $perPage = max(1, min(100, $filters->perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($filters->page, $lastPage));

        $rows = $query
            ->orderByDesc('d.issued_at')
            ->orderByDesc('d.id')
            ->forPage($page, $perPage)
            ->get();

        return new DocumentHistoryPage(
            items: array_map(fn (object $row) => $this->toView($row, $now), $rows->all()),
            total: $total,
            page: $page,
            perPage: $perPage,
            lastPage: $lastPage,
        );
    }

    public function forStudent(int $studentId, ?array $allowedGrades, DateTimeImmutable $now): array
    {
        if ($allowedGrades !== null && $allowedGrades === []) {
            return [];
        }

        $rows = $this->baseQuery($allowedGrades)
            ->where('d.student_id', $studentId)
            ->orderByDesc('d.issued_at')
            ->orderByDesc('d.id')
            ->get();

        return array_map(fn (object $row) => $this->toView($row, $now), $rows->all());
    }

    /**
     * @param  ?list<string>  $allowedGrades
     */
    private function baseQuery(?array $allowedGrades): Builder
    {
        $query = DB::table('issued_documents as d')
            ->leftJoin('users as issuer', 'issuer.id', '=', 'd.issued_by')
            ->leftJoin('users as voider', 'voider.id', '=', 'd.voided_by')
            ->select([
                'd.id',
                'd.student_id',
                'd.student_full_name_snapshot',
                'd.grade_code',
                'd.kind',
                'd.school_year_id',
                'd.term',
                'd.period_label',
                'd.code_hint',
                'd.issued_at',
                'd.expires_at',
                'd.voided_at',
                'd.void_reason',
                'issuer.name as issued_by_name',
                'voider.name as voided_by_name',
            ]);

        if ($allowedGrades !== null) {
            $query->whereIn('d.grade_code', $allowedGrades);
        }

        return $query;
    }

    private function toView(object $row, DateTimeImmutable $now): IssuedDocumentView
    {
        $kind = DocumentKind::from((string) $row->kind);
        $expiresAt = $row->expires_at === null ? null : new DateTimeImmutable((string) $row->expires_at);
        $voidedAt = $row->voided_at === null ? null : new DateTimeImmutable((string) $row->voided_at);

        $status = match (true) {
            $voidedAt !== null => DocumentStatus::Voided,
            $expiresAt !== null && $now > $expiresAt => DocumentStatus::Expired,
            default => DocumentStatus::Valid,
        };

        return new IssuedDocumentView(
            id: (int) $row->id,
            studentId: (int) $row->student_id,
            studentFullName: (string) $row->student_full_name_snapshot,
            gradeCode: (string) $row->grade_code,
            kind: $kind->value,
            kindLabel: $kind->label(),
            schoolYearId: $row->school_year_id === null ? null : (int) $row->school_year_id,
            term: $row->term === null ? null : (int) $row->term,
            periodLabel: (string) $row->period_label,
            codeHint: (string) $row->code_hint,
            status: $status->value,
            statusLabel: $status->label(),
            downloadable: $voidedAt === null,
            issuedAt: (new DateTimeImmutable((string) $row->issued_at))->format('Y-m-d H:i'),
            issuedByName: (string) ($row->issued_by_name ?? ''),
            expiresAt: $expiresAt?->format('Y-m-d'),
            voidedAt: $voidedAt?->format('Y-m-d H:i'),
            voidedByName: $row->voided_by_name === null ? null : (string) $row->voided_by_name,
            voidReason: $row->void_reason === null ? null : (string) $row->void_reason,
        );
    }
}
