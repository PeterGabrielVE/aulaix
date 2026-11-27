<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-005: the two audit logs. audit_entries holds each institution's
 * changes (standard tenant RLS); platform_audit_entries holds changes to the
 * global identity, which no institution may see (RLS that only lets rows
 * through while no institution is active).
 *
 * actor_user_id has no foreign key on purpose: an entry must outlive the
 * account that made the change (AUD-R05.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $this->auditColumns($table);

            $table->index(['institution_id', 'occurred_at']);
            $table->index(['institution_id', 'event_type', 'occurred_at']);
            $table->index(['institution_id', 'actor_user_id', 'occurred_at']);
            $table->index(['institution_id', 'subject_type', 'subject_id']);
        });

        RowLevelSecurity::enable('audit_entries');

        Schema::create('platform_audit_entries', function (Blueprint $table) {
            $table->id();
            $this->auditColumns($table);

            $table->index('occurred_at');
            $table->index(['event_type', 'occurred_at']);
        });

        RowLevelSecurity::enableOutsideTenants('platform_audit_entries');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_entries');
        Schema::dropIfExists('audit_entries');
    }

    private function auditColumns(Blueprint $table): void
    {
        $table->timestampTz('occurred_at');
        $table->string('event_type', 100);
        $table->string('subject_type', 100);
        $table->string('subject_id', 64);
        $table->unsignedBigInteger('actor_user_id')->nullable();
        $table->string('origin', 10);
        $table->ipAddress('ip')->nullable();
        $table->string('operator', 100)->nullable();
        $table->jsonb('changes')->default('{}');
    }
};
