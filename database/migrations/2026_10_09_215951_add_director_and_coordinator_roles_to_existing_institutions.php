<?php

use App\Models\Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Give every existing institution the Director and Coordinador roles
     * (the base set grew from four roles to six). The seeder is idempotent:
     * existing roles, and who holds them, are left as they are.
     */
    public function up(): void
    {
        Institution::query()->each(
            fn (Institution $institution) => RolePermissionSeeder::seedForInstitution($institution)
        );
    }

    /**
     * Reverse the migrations. Removes both roles, and with them every
     * assignment of them, from all institutions.
     */
    public function down(): void
    {
        Role::query()->whereIn('name', ['Director', 'Coordinador'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
