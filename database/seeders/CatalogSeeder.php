<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Every global catalog (F1-04) and nothing else: safe to run in production,
 * on every deploy, without creating demo institutions or users.
 *
 *     php artisan db:seed --class=CatalogSeeder --force
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GeographicCatalogSeeder::class,
            SubjectSeeder::class,
        ]);
    }
}
