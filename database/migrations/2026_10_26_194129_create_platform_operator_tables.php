<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-014 (ADR 0010): the platform's own operators, apart from every
 * account of the institutions. Invisible while an institution is active,
 * like the platform audit log.
 */
return new class extends Migration
{
    private const TABLES = ['platform_operator_recovery_codes', 'platform_operator_invitations', 'platform_operators'];

    public function up(): void
    {
        Schema::create('platform_operators', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('role', 16);
            $table->string('status', 16);
            // Encrypted with the application key (PLT-NF02).
            $table->text('totp_secret')->nullable();
            $table->bigInteger('totp_last_step')->nullable();
            $table->integer('session_version')->default(1);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE platform_operators ADD CONSTRAINT platform_operators_role_known CHECK (role IN ('support', 'super_admin'))");
        DB::statement("ALTER TABLE platform_operators ADD CONSTRAINT platform_operators_status_known CHECK (status IN ('pending', 'active', 'inactive'))");

        Schema::create('platform_operator_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('platform_operators')->cascadeOnDelete();
            $table->string('purpose', 16);
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->foreignId('invited_by')->nullable()->constrained('platform_operators')->nullOnDelete();
            $table->timestamps();

            $table->index('operator_id');
        });

        DB::statement("ALTER TABLE platform_operator_invitations ADD CONSTRAINT platform_operator_invitations_purpose_known CHECK (purpose IN ('onboarding', 'mfa_reset'))");

        Schema::create('platform_operator_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('platform_operators')->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->timestampTz('used_at')->nullable();

            $table->index('operator_id');
        });

        foreach (self::TABLES as $table) {
            RowLevelSecurity::enableOutsideTenants($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
};
