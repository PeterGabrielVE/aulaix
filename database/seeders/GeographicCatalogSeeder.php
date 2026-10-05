<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Parish;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * Seeds the global states/municipalities/parishes catalog (F1-04) with one
 * representative municipality + parish per state (its capital).
 *
 * This is intentionally NOT a full DIVIPOLA import — Venezuela has 335
 * municipalities and 1000+ parishes, far more than a seed needs to prove
 * out the catalog. "code" values here are sequential placeholders, not
 * official DIVIPOLA codes. Swap database/seeders/data/venezuela-divisions.json
 * for the full official dataset when this catalog needs to be exhaustive.
 */
class GeographicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = json_decode(
            file_get_contents(__DIR__.'/data/venezuela-divisions.json'),
            associative: true,
        );

        foreach ($divisions as $index => $division) {
            $sequence = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            $state = State::query()->updateOrCreate(
                ['code' => "VE-{$sequence}"],
                ['name' => $division['state']],
            );

            $municipality = Municipality::query()->updateOrCreate(
                ['code' => "VE-{$sequence}-01"],
                ['state_id' => $state->id, 'name' => $division['municipality']],
            );

            Parish::query()->updateOrCreate(
                ['code' => "VE-{$sequence}-01-01"],
                ['municipality_id' => $municipality->id, 'name' => $division['parish']],
            );
        }
    }
}
