<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-009 EST-R08.4, R08.5: the corrections guardians ask for, in their
 * institution under Row Level Security (S-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('text', 1000);
            $table->string('status', 16);
            $table->timestampTz('submitted_at');
            $table->timestampTz('resolved_at')->nullable();

            $table->index(['institution_id', 'status', 'submitted_at']);
            $table->index('guardian_id');
        });

        DB::statement("ALTER TABLE correction_requests ADD CONSTRAINT correction_requests_status_known CHECK (status IN ('pending', 'resolved'))");
        RowLevelSecurity::enable('correction_requests');
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
