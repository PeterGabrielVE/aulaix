<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use AulaX\Students\Application\Correction\CorrectionList;
use AulaX\Students\Application\Correction\CorrectionView;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentCorrectionList implements CorrectionList
{
    private const int LIMIT = 200;

    public function all(): array
    {
        return $this->views($this->query()->orderByRaw("correction_requests.status = 'pending' desc"));
    }

    public function ofGuardian(int $guardianId): array
    {
        return $this->views($this->query()->where('correction_requests.guardian_id', $guardianId));
    }

    private function query(): Builder
    {
        return DB::table('correction_requests')
            ->join('guardians', 'guardians.id', '=', 'correction_requests.guardian_id')
            ->leftJoin('students', 'students.id', '=', 'correction_requests.student_id')
            ->select([
                'correction_requests.*',
                DB::raw("guardians.given_names || ' ' || guardians.family_names as guardian_name"),
                DB::raw("students.family_names || ', ' || students.given_names as student_name"),
            ]);
    }

    /**
     * @return list<CorrectionView>
     */
    private function views(Builder $query): array
    {
        return $query
            ->orderByDesc('correction_requests.submitted_at')
            ->orderByDesc('correction_requests.id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (object $row) => new CorrectionView(
                (int) $row->id,
                $row->guardian_name,
                $row->student_name,
                $row->text,
                $row->status,
                (new DateTimeImmutable($row->submitted_at))->format('Y-m-d'),
            ))
            ->all();
    }
}
