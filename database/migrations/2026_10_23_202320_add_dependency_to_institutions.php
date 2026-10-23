<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-006 INS-R02.4, R02.5 (through SPEC-013): which administration a
 * public institution depends on. Existing institutions get none: SPEC-013
 * asks for it as a pending task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('dependency', 16)->nullable()->after('type');
        });

        DB::statement("ALTER TABLE institutions ADD CONSTRAINT institutions_dependency_public CHECK (dependency IS NULL OR (type IS NOT DISTINCT FROM 'publica' AND dependency IN ('nacional', 'estadal', 'municipal')))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE institutions DROP CONSTRAINT IF EXISTS institutions_dependency_public');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('dependency');
        });
    }
};
