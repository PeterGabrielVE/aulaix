<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use AulaX\Catalogs\Application\Geography\GeographyDirectory;

final class EloquentGeographyDirectory implements GeographyDirectory
{
    public function states(): array
    {
        return self::options(StateModel::query()->orderBy('name')->get(['id', 'name']));
    }

    public function municipalitiesOf(int $stateId): array
    {
        return self::options(MunicipalityModel::query()->where('state_id', $stateId)->orderBy('name')->get(['id', 'name']));
    }

    public function parishesOf(int $municipalityId): array
    {
        return self::options(ParishModel::query()->where('municipality_id', $municipalityId)->orderBy('name')->get(['id', 'name']));
    }

    public function stateExists(int $stateId): bool
    {
        return StateModel::query()->whereKey($stateId)->exists();
    }

    public function parishExists(int $parishId): bool
    {
        return ParishModel::query()->whereKey($parishId)->exists();
    }

    public function locationOf(int $parishId): ?array
    {
        $parish = ParishModel::query()->with('municipality:id,state_id')->find($parishId, ['id', 'municipality_id']);

        return $parish === null ? null : [
            'state_id' => (int) $parish->municipality->state_id,
            'municipality_id' => (int) $parish->municipality_id,
            'parish_id' => (int) $parish->id,
        ];
    }

    /**
     * @param  iterable<object>  $rows
     * @return list<array{id: int, name: string}>
     */
    private static function options(iterable $rows): array
    {
        $options = [];

        foreach ($rows as $row) {
            $options[] = ['id' => (int) $row->id, 'name' => $row->name];
        }

        return $options;
    }
}
