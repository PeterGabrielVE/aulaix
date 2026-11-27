<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-010: each student in a section of a school year, for a period, with
 * its promotion result and pending subjects. Rows are never deleted: an
 * ended or annulled enrollment stays in the history, and it keeps its
 * student, year and section (RESTRICT). Under Row Level Security (S-01).
 */
return new class extends Migration
{
    private const TABLES = ['enrollment_pending_subjects', 'enrollments'];

    private const GRADES = "'maternal_1', 'maternal_2', 'maternal_3', 'preescolar_1', 'preescolar_2', 'preescolar_3', "
        ."'primaria_1', 'primaria_2', 'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6', "
        ."'media_1', 'media_2', 'media_3', 'media_4', 'media_5'";

    public function up(): void
    {
        $grades = self::GRADES;

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->string('grade', 16);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('end_reason', 16)->nullable();
            $table->string('end_note', 500)->nullable();
            $table->string('over_capacity_reason', 500)->nullable();
            $table->string('age_exception_reason', 500)->nullable();
            // Accounts, kept as history: no foreign key, like the audit log.
            $table->unsignedBigInteger('age_exception_by')->nullable();
            $table->timestampTz('registered_at');
            $table->unsignedBigInteger('registered_by');
            $table->string('annulment_reason', 16)->nullable();
            $table->string('annulment_note', 500)->nullable();
            $table->unsignedBigInteger('annulled_by')->nullable();
            $table->timestampTz('annulled_at')->nullable();
            $table->string('outcome', 24)->nullable();
            $table->timestampTz('outcome_recorded_at')->nullable();
            $table->unsignedBigInteger('outcome_recorded_by')->nullable();
            $table->foreignId('previous_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->timestamps();

            // The capacity and the list of a section (MAT-R02, R09).
            $table->index('section_id', 'enrollments_current_by_section')->where('ends_on is null');
            // Coordinators and the enrollment of a whole year (MAT-R07, R08).
            $table->index(['school_year_id', 'grade'], 'enrollments_current_by_grade')->where('ends_on is null');
            // The history of a student (MAT-R04.6).
            $table->index(['student_id', 'starts_on']);
        });

        // One current enrollment per student and school year (MAT-R01.2, NF03).
        DB::statement('CREATE UNIQUE INDEX enrollments_one_current_per_year ON enrollments (student_id, school_year_id) WHERE ends_on IS NULL');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_grade_known CHECK (grade IN ({$grades}))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_end_has_reason CHECK ((ends_on IS NULL) = (end_reason IS NULL))');
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_ends_after_start CHECK (ends_on IS NULL OR ends_on >= starts_on)');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_end_reason_known CHECK (end_reason IN ('section_change', 'grade_change', 'withdrawal', 'transfer', 'annulled'))");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_annulment_is_complete CHECK (
            (end_reason IS NOT DISTINCT FROM 'annulled') = (annulment_reason IS NOT NULL AND annulled_by IS NOT NULL AND annulled_at IS NOT NULL)
            AND (annulment_reason IS NULL) = (annulled_by IS NULL) AND (annulled_by IS NULL) = (annulled_at IS NULL)
        )");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_annulment_reason_known CHECK (annulment_reason IN ('typing_error', 'duplicate', 'other'))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_age_exception_is_complete CHECK ((age_exception_reason IS NULL) = (age_exception_by IS NULL))');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_outcome_known CHECK (outcome IN ('promoted', 'repeats', 'promoted_with_pending', 'lost_by_absence'))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_outcome_is_complete CHECK ((outcome IS NULL) = (outcome_recorded_at IS NULL) AND (outcome IS NULL) = (outcome_recorded_by IS NULL))');

        Schema::create('enrollment_pending_subjects', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();

            $table->unique(['enrollment_id', 'subject_id']);
        });

        foreach (self::TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }
};
