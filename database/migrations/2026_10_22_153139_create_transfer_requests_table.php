<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-009 (ADR 0008): the transfer requests between institutions. A request
 * belongs to the destination (institution_id) and names no origin until it
 * is answered; its Row Level Security lets the institution with that
 * student active see and answer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution();
            $table->string('destination_name');
            $table->string('identification', 12);
            $table->string('status', 16);
            $table->foreignId('origin_institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->string('origin_name')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('answered_at')->nullable();
        });

        DB::statement("ALTER TABLE transfer_requests ADD CONSTRAINT transfer_requests_status_known CHECK (status IN ('pending', 'approved', 'rejected', 'expired'))");
        // One pending request per student in the destination; the origin finds them by identification.
        DB::statement("CREATE UNIQUE INDEX transfer_requests_one_pending ON transfer_requests (institution_id, identification) WHERE status = 'pending'");
        DB::statement("CREATE INDEX transfer_requests_pending_by_identification ON transfer_requests (identification) WHERE status = 'pending'");
        DB::statement('CREATE INDEX transfer_requests_origin ON transfer_requests (origin_institution_id) WHERE origin_institution_id IS NOT NULL');

        RowLevelSecurity::enableAddressedByIdentification('transfer_requests', 'students');
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};
