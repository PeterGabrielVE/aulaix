<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use AulaX\Catalogs\Application\Subjects;
use Illuminate\Support\Facades\DB;

final class EloquentSubjects implements Subjects
{
    public function ofLevel(string $level): array
    {
        return SubjectModel::query()
            ->where('education_level', $level)
            ->orderByRaw('name collate "es-x-icu"')
            ->get(['id', 'code', 'name'])
            ->map(fn (SubjectModel $subject) => ['id' => (int) $subject->id, 'code' => $subject->code, 'name' => $subject->name])
            ->all();
    }

    public function levelsOf(array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        return DB::table('subjects')
            ->whereIn('id', $subjectIds)
            ->pluck('education_level', 'id')
            ->mapWithKeys(fn (string $level, int|string $id) => [(int) $id => $level])
            ->all();
    }
}
