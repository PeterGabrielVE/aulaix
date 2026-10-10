<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Deliberately does NOT use WithoutModelEvents: it would silently
     * suppress the Eloquent `creating` hooks that spatie/laravel-permission
     * uses to stamp roles with their institution (team) id, and the one
     * BelongsToInstitution uses to stamp tenant-scoped models.
     */
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
            InstitutionSeeder::class,
        ]);
    }
}
