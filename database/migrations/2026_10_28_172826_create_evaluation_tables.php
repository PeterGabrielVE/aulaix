<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-011: the activities of the plan of a subject in a section and a term,
 * the grade of each student in each one (hundredths, 100 – 2000, or NP), and
 * the literals and reports of Primaria and Inicial. "Not applicable" is not
 * stored: it follows from the enrollment periods. Under Row Level Security
 * (S-01).
 */
return new class extends Migration
{
    private const TABLES = ['student_marks', 'activity_grades', 'evaluation_activities', 'evaluation_plans'];

    public function up(): void
    {
        // One row per plan: two first saves of the same plan collide here
        // instead of adding up to 200 %, and the audit log has its id.
        Schema::create('evaluation_plans', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('term');
            $table->timestamps();

            $table->unique(['school_year_id', 'section_id', 'subject_id', 'term'], 'evaluation_plans_one_per_sheet');
            // The plans of a subject in a term, of every section (EVA-R02.7).
            $table->index(['school_year_id', 'subject_id', 'term']);
        });

        DB::statement('ALTER TABLE evaluation_plans ADD CONSTRAINT evaluation_plans_term_known CHECK (term BETWEEN 1 AND 3)');

        Schema::create('evaluation_activities', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('plan_id')->constrained('evaluation_plans')->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('date');
            // Hundredths of a percent: the plan adds up to 10000 (EVA-R01.2).
            $table->unsignedInteger('weight');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['plan_id', 'position']);
        });

        DB::statement('ALTER TABLE evaluation_activities ADD CONSTRAINT evaluation_activities_weight_positive CHECK (weight > 0)');

        Schema::create('activity_grades', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('activity_id')->constrained('evaluation_activities')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // Hundredths: 9,5 is 950.
            $table->unsignedInteger('score')->nullable();
            $table->boolean('not_presented')->default(false);
            $table->timestamps();

            $table->unique(['activity_id', 'student_id']);
            // A student's grades in a year (the final grade, SPEC-010 outcome).
            $table->index('student_id');
        });

        // "score IS NOT NULL": NULL BETWEEN … is NULL, and a CHECK lets NULL through.
        DB::statement('ALTER TABLE activity_grades ADD CONSTRAINT activity_grades_score_or_not_presented CHECK (
            (not_presented AND score IS NULL) OR (NOT not_presented AND score IS NOT NULL AND score BETWEEN 100 AND 2000)
        )');

        Schema::create('student_marks', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->string('period', 5);
            $table->string('kind', 8);
            $table->char('literal', 1)->nullable();
            $table->text('description')->nullable();
            $table->boolean('incomplete')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'school_year_id', 'period', 'kind']);
        });

        DB::statement("ALTER TABLE student_marks ADD CONSTRAINT student_marks_period_known CHECK (period IN ('1', '2', '3', 'final'))");
        DB::statement("ALTER TABLE student_marks ADD CONSTRAINT student_marks_kind_known CHECK (kind IN ('literal', 'report'))");
        DB::statement("ALTER TABLE student_marks ADD CONSTRAINT student_marks_literal_known CHECK (literal IN ('A', 'B', 'C', 'D', 'E'))");
        DB::statement('ALTER TABLE student_marks ADD CONSTRAINT student_marks_description_length CHECK (char_length(description) <= 5000)');

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
