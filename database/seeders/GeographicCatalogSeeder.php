<?php

namespace Database\Seeders;

use AulaX\Catalogs\Application\SyncGeographyHandler;
use Illuminate\Database\Seeder;

/**
 * Seeds the global states/municipalities/parishes catalog (F1-04): the 24
 * federal entities (23 states + Distrito Capital), their 335 municipalities
 * and 1,140 parishes, shared by every institution.
 *
 * A thin entry point: the data and its coding live in
 * AulaX\Catalogs\Infrastructure\Source\JsonGeographySource, and the sync
 * rules (additive, idempotent, provisional catalog removed) in
 * AulaX\Catalogs\Application\SyncGeographyHandler.
 */
class GeographicCatalogSeeder extends Seeder
{
    public function run(SyncGeographyHandler $syncGeography): void
    {
        $syncGeography();
    }
}
