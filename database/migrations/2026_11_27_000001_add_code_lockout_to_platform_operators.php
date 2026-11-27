<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-017 RSP-R03.2: stores consecutive failed code attempts (capped at 10)
 * and the 15-minute lockout timestamp on each platform operator row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE platform_operators ADD COLUMN code_failures jsonb NOT NULL DEFAULT '[]'::jsonb");
        DB::statement('ALTER TABLE platform_operators ADD COLUMN blocked_until timestamptz NULL');
        DB::statement('ALTER TABLE platform_operators ADD CONSTRAINT platform_operators_code_failures_limit CHECK (jsonb_array_length(code_failures) <= 10)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE platform_operators DROP CONSTRAINT IF EXISTS platform_operators_code_failures_limit');
        DB::statement('ALTER TABLE platform_operators DROP COLUMN IF EXISTS blocked_until');
        DB::statement('ALTER TABLE platform_operators DROP COLUMN IF EXISTS code_failures');
    }
};
