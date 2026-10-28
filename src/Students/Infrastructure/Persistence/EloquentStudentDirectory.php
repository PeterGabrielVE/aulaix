<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use AulaX\Students\Application\Enrollable\EnrollableStudent;
use AulaX\Students\Application\FindGuardian\GuardianView;
use AulaX\Students\Application\ListStudents\StudentListItem;
use AulaX\Students\Application\ListStudents\StudentPage;
use AulaX\Students\Application\ShowStudent\GuardianLinkView;
use AulaX\Students\Application\ShowStudent\HealthView;
use AulaX\Students\Application\ShowStudent\StudentView;
use AulaX\Students\Application\ShowStudent\WithdrawalView;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Domain\Identity\GuardianDocument;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads straight from the tables of the open institution (Row Level
 * Security filters every query). The search is a plain ILIKE: within one
 * institution it stays under EST-NF03 without a trigram index.
 */
final class EloquentStudentDirectory implements StudentDirectory
{
    public function page(string $search, bool $includeWithdrawn, int $page, int $perPage, ?array $onlyIds = null): StudentPage
    {
        $query = DB::table('students')
            ->when($onlyIds !== null, fn (Builder $query) => $query->whereIn('id', $onlyIds))
            ->when(! $includeWithdrawn, fn (Builder $query) => $query->where('status', 'active'))
            ->when($search !== '', fn (Builder $query) => $this->matching($query, $search));

        $total = (clone $query)->count();
        // Spanish order (Á with A, Ñ after N), whatever the database's default collation.
        $rows = $query
            ->orderByRaw('family_names collate "es-x-icu", given_names collate "es-x-icu", id')
            ->forPage($page, $perPage)
            ->get(['id', 'given_names', 'family_names', 'identification_type', 'identification', 'status']);

        return new StudentPage(
            $rows->map(fn (object $row) => new StudentListItem(
                (int) $row->id,
                "{$row->family_names}, {$row->given_names}",
                $row->identification_type,
                $row->identification,
                $row->status,
            ))->all(),
            $total,
            $page,
            $perPage,
        );
    }

    public function enrollable(?array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('students')
            ->when($ids === null, fn (Builder $query) => $query->where('status', 'active'))
            ->when($ids !== null, fn (Builder $query) => $query->whereIn('id', $ids))
            ->orderByRaw('family_names collate "es-x-icu", given_names collate "es-x-icu", id')
            ->get(['id', 'given_names', 'family_names', 'identification', 'birth_date', 'status'])
            ->map(fn (object $row) => new EnrollableStudent(
                (int) $row->id,
                "{$row->family_names}, {$row->given_names}",
                $row->identification,
                (string) $row->birth_date,
                $row->status === 'active',
            ))
            ->all();
    }

    public function student(int $id, bool $includeHealth): ?StudentView
    {
        $row = DB::table('students')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        $guardians = DB::table('guardianships')
            ->join('guardians', 'guardians.id', '=', 'guardianships.guardian_id')
            ->where('guardianships.student_id', $id)
            ->orderByDesc('guardianships.is_primary')
            ->orderBy('guardianships.id')
            ->get(['guardianships.*', 'guardians.given_names', 'guardians.family_names', 'guardians.document_type', 'guardians.document', 'guardians.phone', 'guardians.email', 'guardians.address', 'guardians.account_id'])
            ->map(fn (object $link) => new GuardianLinkView(
                (int) $link->guardian_id,
                $link->given_names,
                $link->family_names,
                $link->document_type,
                $link->document,
                $link->phone,
                $link->email,
                $link->address,
                $link->relationship,
                (bool) $link->is_legal,
                (bool) $link->is_primary,
                $link->account_id !== null,
            ))
            ->all();

        $withdrawals = DB::table('student_withdrawals')
            ->where('student_id', $id)
            ->orderBy('withdrawn_on')
            ->orderBy('id')
            ->get()
            ->map(fn (object $withdrawal) => new WithdrawalView($withdrawal->withdrawn_on, $withdrawal->reason, $withdrawal->note, $withdrawal->reinstated_on))
            ->all();

        $hasHealthInfo = $row->allergies !== null || $row->medical_conditions !== null || $row->disability !== null;

        return new StudentView(
            (int) $row->id,
            $row->given_names,
            $row->family_names,
            $row->sex,
            $row->birth_date,
            $row->nationality,
            $row->identification_type,
            $row->identification,
            DB::table('student_previous_identifications')->where('student_id', $id)->orderBy('id')->pluck('identification')->all(),
            $row->birth_country,
            $row->birth_state_id === null ? null : (int) $row->birth_state_id,
            $row->parish_id === null ? null : (int) $row->parish_id,
            $row->address,
            $row->emergency_phone,
            $row->status,
            $guardians,
            $withdrawals,
            $includeHealth ? new HealthView($row->allergies, $row->medical_conditions, $row->disability) : null,
            $hasHealthInfo,
        );
    }

    public function guardianByDocument(GuardianDocument $document): ?GuardianView
    {
        $row = DB::table('guardians')
            ->where('document_type', $document->type)
            ->where('document', $document->value())
            ->first();

        return $row === null ? null : new GuardianView(
            (int) $row->id,
            $row->given_names,
            $row->family_names,
            $row->document_type,
            $row->document,
            $row->phone,
            $row->email,
            $row->address,
        );
    }

    public function representedBy(int $guardianId): array
    {
        return DB::table('students')
            ->join('guardianships', 'guardianships.student_id', '=', 'students.id')
            ->where('guardianships.guardian_id', $guardianId)
            ->orderByRaw('students.family_names collate "es-x-icu", students.given_names collate "es-x-icu", students.id')
            ->get(['students.id', 'students.given_names', 'students.family_names', 'students.identification_type', 'students.identification', 'students.status'])
            ->map(fn (object $row) => new StudentListItem(
                (int) $row->id,
                "{$row->family_names}, {$row->given_names}",
                $row->identification_type,
                $row->identification,
                $row->status,
            ))
            ->all();
    }

    public function soleLegalGuardianOf(int $guardianId): array
    {
        return DB::table('students')
            ->join('guardianships', 'guardianships.student_id', '=', 'students.id')
            ->where('guardianships.guardian_id', $guardianId)
            ->where('guardianships.is_legal', true)
            ->where('students.status', 'active')
            ->whereNotExists(fn (Builder $other) => $other
                ->selectRaw('1')
                ->from('guardianships as other')
                ->whereColumn('other.student_id', 'students.id')
                ->where('other.guardian_id', '!=', $guardianId)
                ->where('other.is_legal', true))
            ->orderByRaw('students.family_names collate "es-x-icu", students.given_names collate "es-x-icu"')
            ->get(['students.given_names', 'students.family_names'])
            ->map(fn (object $row) => "{$row->given_names} {$row->family_names}")
            ->all();
    }

    /**
     * Names and family names as typed; identifications without the dots,
     * dashes and spaces people type. The LIKE wildcards are escaped (S-09).
     */
    private function matching(Builder $query, string $search): Builder
    {
        $text = '%'.self::escaped($search).'%';
        $identification = strtoupper((string) preg_replace('/[\s.\-]/', '', $search));
        $code = '%'.self::escaped($identification).'%';

        return $query->where(fn (Builder $where) => $where
            ->where('given_names', 'ilike', $text)
            ->orWhere('family_names', 'ilike', $text)
            ->orWhereRaw("given_names || ' ' || family_names ilike ?", [$text])
            ->when($identification !== '', fn (Builder $by) => $by
                ->orWhere('identification', 'like', $code)
                ->orWhereExists(fn (Builder $previous) => $previous
                    ->selectRaw('1')
                    ->from('student_previous_identifications')
                    ->whereColumn('student_previous_identifications.student_id', 'students.id')
                    ->where('student_previous_identifications.identification', 'like', $code))));
    }

    private static function escaped(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
