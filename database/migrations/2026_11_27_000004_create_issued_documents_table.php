<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-012 (ADR 0015): immutable issued institution documents (report cards
 * and certificates) with non-sequential verification code hashes, PDF SHA-256
 * integrity fingerprints, minimal public verification snapshots and Row Level
 * Security (S-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('grade_code', 40);
            $table->string('kind', 40);
            $table->unsignedSmallInteger('term')->nullable();
            $table->char('verification_code_hash', 64);
            $table->string('code_hint', 12);
            $table->string('pdf_path', 255);
            $table->char('pdf_sha256', 64);
            $table->unsignedInteger('pdf_bytes');
            $table->string('student_full_name_snapshot', 255);
            $table->string('student_initials', 32);
            $table->string('student_id_last4', 12);
            $table->string('institution_name_snapshot', 255);
            $table->string('institution_dea_snapshot', 20)->nullable();
            $table->string('period_label', 120);
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'verification_code_hash'], 'issued_documents_institution_code_hash_unique');
            $table->index(['institution_id', 'student_id', 'issued_at'], 'issued_documents_student_history_idx');
            $table->index(['institution_id', 'kind', 'school_year_id', 'issued_at'], 'issued_documents_filter_idx');
            $table->index(['institution_id', 'grade_code', 'issued_at'], 'issued_documents_grade_scope_idx');
        });

        DB::statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_kind_known CHECK (kind IN (
            'term_report',
            'final_report',
            'study_certificate',
            'enrollment_certificate',
            'withdrawal_certificate',
            'good_conduct_certificate',
            'progression_certificate'
        ))");
        DB::statement('ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_term_matches_kind CHECK (
            (kind = \'term_report\' AND term IS NOT NULL AND term BETWEEN 1 AND 3)
            OR (kind <> \'term_report\' AND term IS NULL)
        )');
        DB::statement('ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_pdf_bytes_positive CHECK (pdf_bytes > 0)');
        DB::statement("ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_expiration_matches_kind CHECK (
            (kind IN ('study_certificate', 'enrollment_certificate', 'good_conduct_certificate') AND expires_at IS NOT NULL AND expires_at > issued_at)
            OR (kind NOT IN ('study_certificate', 'enrollment_certificate', 'good_conduct_certificate') AND expires_at IS NULL)
        )");
        DB::statement('ALTER TABLE issued_documents ADD CONSTRAINT issued_documents_void_coherent CHECK (
            (voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL)
            OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL AND char_length(void_reason) BETWEEN 5 AND 500)
        )');

        RowLevelSecurity::enable('issued_documents');
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_documents');
    }
};
