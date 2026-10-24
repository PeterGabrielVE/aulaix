<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-004 (A-09): the account model moved from App\Models\User to the
 * IdentityAccess module and is stored by spatie/laravel-permission under the
 * stable morph alias "user" (IdentityAccessServiceProvider). Without this,
 * every role and permission assignment would point at a class that no
 * longer exists, and everybody would lose their roles.
 *
 * Both tables are exempt from RLS (RLS_EXEMPT_TABLES), so one UPDATE each.
 */
return new class extends Migration
{
    private const TABLES = ['model_has_roles', 'model_has_permissions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('model_type', 'App\Models\User')->update(['model_type' => 'user']);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::table($table)->where('model_type', 'user')->update(['model_type' => 'App\Models\User']);
        }
    }
};
