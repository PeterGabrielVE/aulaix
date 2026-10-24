<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use AulaX\Catalogs\Application\Port\GeographyStore;
use AulaX\Catalogs\Domain\Geography\GeographicCatalog;
use AulaX\Catalogs\Domain\Geography\LegacyPlaceholder;

/**
 * Rows are matched by code, so re-running the sync updates names (and
 * parents) in place and never duplicates. Official rows are never deleted.
 */
final class EloquentGeographyStore implements GeographyStore
{
    /**
     * Municipalities and parishes cascade with their state; an institution
     * located in one of them keeps existing, without a parish
     * (institutions.parish_id is nullOnDelete).
     */
    public function removeLegacyPlaceholders(): void
    {
        StateModel::query()->where('code', '~', LegacyPlaceholder::STATE_CODE_PATTERN)->delete();
    }

    public function upsert(GeographicCatalog $catalog): void
    {
        $states = $catalog->states();

        StateModel::query()->upsert(
            array_map(fn ($state) => ['code' => $state->code->value, 'name' => $state->name], $states),
            uniqueBy: ['code'],
            update: ['name'],
        );
        $stateIds = StateModel::query()->pluck('id', 'code');

        $municipalities = [];
        foreach ($states as $state) {
            foreach ($state->municipalities as $municipality) {
                $municipalities[] = [
                    'code' => $municipality->code->value,
                    'name' => $municipality->name,
                    'state_id' => $stateIds[$state->code->value],
                ];
            }
        }
        MunicipalityModel::query()->upsert($municipalities, uniqueBy: ['code'], update: ['name', 'state_id']);
        $municipalityIds = MunicipalityModel::query()->pluck('id', 'code');

        $parishes = [];
        foreach ($states as $state) {
            foreach ($state->municipalities as $municipality) {
                foreach ($municipality->parishes as $parish) {
                    $parishes[] = [
                        'code' => $parish->code->value,
                        'name' => $parish->name,
                        'municipality_id' => $municipalityIds[$municipality->code->value],
                    ];
                }
            }
        }
        ParishModel::query()->upsert($parishes, uniqueBy: ['code'], update: ['name', 'municipality_id']);
    }
}
