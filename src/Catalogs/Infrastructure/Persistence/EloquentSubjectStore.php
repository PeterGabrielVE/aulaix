<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use AulaX\Catalogs\Application\Port\SubjectStore;
use AulaX\Catalogs\Domain\Subject\SubjectCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectEntry;

final class EloquentSubjectStore implements SubjectStore
{
    public function replaceWith(SubjectCatalog $catalog): void
    {
        SubjectModel::query()->upsert(
            array_map(fn (SubjectEntry $entry) => [
                'code' => $entry->code->value,
                'name' => $entry->name,
                'education_level' => $entry->level->value,
            ], $catalog->entries()),
            uniqueBy: ['code'],
            update: ['name', 'education_level'],
        );

        SubjectModel::query()->whereNotIn('code', $catalog->codes())->delete();
    }
}
