<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-004 (ADR 0004): memberships (a person's relation with each
 * institution) and invitations (an institution's pending offer to an email),
 * both scoped by institution with the standard Row Level Security; plus the
 * columns of the global account. Data moves in the next migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_memberships', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('suspension_reason', 20)->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'user_id']);
            $table->index('user_id');
        });

        RowLevelSecurity::enable('institution_memberships');

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution();
            $table->string('email');
            $table->string('name');
            $table->jsonb('roles')->default('[]');
            $table->string('token_hash');
            $table->timestampTz('expires_at');
            $table->string('status', 20)->default('pending');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One pending invitation per email and institution.
        DB::statement("CREATE UNIQUE INDEX invitations_pending_email_unique ON invitations (institution_id, email) WHERE status = 'pending'");

        RowLevelSecurity::enable('invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_state', 20)->default('active');
            $table->string('consolidation_state', 10)->nullable();
            $table->timestampTz('deletion_requested_at')->nullable();
            $table->timestampTz('deletion_due_at')->nullable();
            $table->string('pending_email')->nullable();
            $table->string('pending_email_token_hash')->nullable();
            $table->timestampTz('pending_email_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_state', 'consolidation_state', 'deletion_requested_at', 'deletion_due_at',
                'pending_email', 'pending_email_token_hash', 'pending_email_expires_at',
            ]);
        });

        Schema::dropIfExists('invitations');
        Schema::dropIfExists('institution_memberships');
    }
};
