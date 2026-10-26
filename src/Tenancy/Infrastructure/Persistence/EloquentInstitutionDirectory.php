<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Tenancy\Application\Administration\InstitutionPage;
use AulaX\Tenancy\Application\SearchInstitutions\InstitutionDirectory;
use AulaX\Tenancy\Application\SearchInstitutions\InstitutionSummary;

final class EloquentInstitutionDirectory implements InstitutionDirectory
{
    private const array COLUMNS = ['id', 'name', 'subdomain', 'type', 'status', 'created_at'];

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

    public function page(string $text, ?string $status, int $page, int $perPage): InstitutionPage
    {
        $query = InstitutionModel::query()
            ->when($text !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', '%'.self::escapeLike($text).'%')
                ->orWhere('subdomain', 'ilike', '%'.self::escapeLike($text).'%')))
            ->when($status !== null, fn ($query) => $query->where('status', $status));

        $total = (clone $query)->count();

        $items = $query->orderBy('name')
            ->forPage($page, $perPage)
            ->get(self::COLUMNS)
            ->map(self::row(...))
            ->values()
            ->all();

        return new InstitutionPage($items, $total, $page, $perPage);
    }

    public function one(int $id): ?array
    {
        $institution = InstitutionModel::query()->find($id, self::COLUMNS);

        return $institution === null ? null : self::row($institution);
    }

    /**
     * @return array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}
     */
    private static function row(InstitutionModel $institution): array
    {
        return [
            'id' => (int) $institution->id,
            'name' => $institution->name,
            'subdomain' => $institution->subdomain,
            'type' => $institution->type,
            'status' => $institution->status->value,
            'createdAt' => $institution->created_at?->toDateString() ?? '',
        ];
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
