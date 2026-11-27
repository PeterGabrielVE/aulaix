<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-006: the type and levels of each institution, and the signatures of
 * its authorities. Existing institutions get no type and no levels: there is
 * no right value to assume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('type', 16)->nullable()->after('status');
            $table->jsonb('education_levels')->default('[]')->after('type');
        });

        DB::statement("ALTER TABLE institutions ADD CONSTRAINT institutions_type_known CHECK (type IS NULL OR type IN ('publica', 'privada', 'subvencionada'))");

        Schema::create('institution_signatures', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->string('role', 16);
            $table->string('signer_name', 120);
            $table->string('signer_title', 120);
            $table->string('file_path');
            $table->timestamps();

            $table->unique(['institution_id', 'role']);
        });

        DB::statement("ALTER TABLE institution_signatures ADD CONSTRAINT institution_signatures_role_known CHECK (role IN ('director', 'secretary'))");
        RowLevelSecurity::enable('institution_signatures');
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_signatures');

        DB::statement('ALTER TABLE institutions DROP CONSTRAINT IF EXISTS institutions_type_known');

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['type', 'education_levels']);
        });
    }
};
