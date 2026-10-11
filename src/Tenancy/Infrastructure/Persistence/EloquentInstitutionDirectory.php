<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Tenancy\Application\SearchInstitutions\InstitutionDirectory;
use AulaX\Tenancy\Application\SearchInstitutions\InstitutionSummary;

final class EloquentInstitutionDirectory implements InstitutionDirectory
{
    public function searchActive(string $text, int $limit): array
    {
        return InstitutionModel::query()
            ->active()
            ->when($text !== '', fn ($query) => $query->where('name', 'ilike', '%'.self::escapeLike($text).'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get(['name', 'subdomain'])
            ->map(fn (InstitutionModel $institution) => new InstitutionSummary($institution->name, $institution->subdomain))
            ->all();
    }

    /**
     * PostgreSQL's LIKE escapes with a backslash by default, so escaping the
     * backslash itself first makes %, _ and \ match literally (TEN-R04.6).
     */
    private static function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }
}
