<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * Seeds the global states/municipalities/parishes catalog (F1-04): the 24
 * federal entities (23 states + Distrito Capital), their 335 municipalities
 * and 1,140 parishes, shared by every institution.
 *
 * Data: database/seeders/data/venezuela-divisions.json, generated from the
 * public dataset github.com/zokeber/venezuela-json with two corrections —
 * Vargas renamed La Guaira (2019), and the missing parish of Bolívar
 * (Sucre), Mariguitar.
 *
 * Codes: states use their ISO 3166-2:VE code (VE-A = Distrito Capital).
 * Municipalities and parishes have no ISO code, so theirs are internal and
 * positional within that file (VE-A-01, VE-A-01-01): never reorder the
 * file, only append, or existing codes would point at different places.
 *
 * Idempotent: rows are matched by code, so re-running it updates names in
 * place and never duplicates.
 */
class GeographicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $states = json_decode(
            file_get_contents(__DIR__.'/data/venezuela-divisions.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->removePlaceholderCatalog();

        State::query()->upsert(
            array_map(fn (array $state) => ['code' => $state['code'], 'name' => $state['name']], $states),
            uniqueBy: ['code'],
            update: ['name'],
        );
        $stateIds = State::query()->pluck('id', 'code');

        $municipalities = [];
        foreach ($states as $state) {
            foreach ($state['municipalities'] as $municipality) {
                $municipalities[] = [
                    'code' => $municipality['code'],
                    'name' => $municipality['name'],
                    'state_id' => $stateIds[$state['code']],
                ];
            }
        }
        Municipality::query()->upsert($municipalities, uniqueBy: ['code'], update: ['name', 'state_id']);
        $municipalityIds = Municipality::query()->pluck('id', 'code');

        $parishes = [];
        foreach ($states as $state) {
            foreach ($state['municipalities'] as $municipality) {
                foreach ($municipality['parishes'] as $parish) {
                    $parishes[] = [
                        'code' => $parish['code'],
                        'name' => $parish['name'],
                        'municipality_id' => $municipalityIds[$municipality['code']],
                    ];
                }
            }
        }
        Parish::query()->upsert($parishes, uniqueBy: ['code'], update: ['name', 'municipality_id']);
    }

    /**
     * The first version of this catalog held one placeholder municipality
     * and parish per state, coded VE-01 … VE-24. Its codes don't match the
     * real ones, so those rows are dropped (municipalities and parishes
     * cascade; an institution located in one keeps existing, without a
     * parish) rather than left behind as duplicates.
     */
    private function removePlaceholderCatalog(): void
    {
        State::query()->where('code', '~', '^VE-[0-9]{2}$')->delete();
    }
}
