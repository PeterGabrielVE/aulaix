<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-004 B-06: one account per person, so one password and one reset
 * token per email, requested on the central domain (IDG-R06). No
 * institution may ever read the tokens.
 *
 * Tokens live 60 minutes and the old ones were per institution, so the
 * table is rebuilt rather than migrated: links sent before the deploy stop
 * working and have to be requested again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        RowLevelSecurity::enableOutsideTenants('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->belongsToInstitution(index: false);
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->primary(['institution_id', 'email']);
        });

        RowLevelSecurity::enable('password_reset_tokens');
    }
};
