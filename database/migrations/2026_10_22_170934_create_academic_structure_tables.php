<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-008: the structure of each school year (sections, curriculum, who
 * teaches, guides and coordinates), the base SPEC-010 and SPEC-011 build on.
 * Every table belongs to its institution under Row Level Security (S-01).
 */
return new class extends Migration
{
    private const TABLES = ['coordinator_assignments', 'teaching_assignments', 'curriculum_subjects', 'sections'];

    private const GRADES = "'maternal_1', 'maternal_2', 'maternal_3', 'preescolar_1', 'preescolar_2', 'preescolar_3', "
        ."'primaria_1', 'primaria_2', 'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6', "
        ."'media_1', 'media_2', 'media_3', 'media_4', 'media_5'";

    public function up(): void
    {
        $grades = self::GRADES;

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 16);
            $table->char('letter', 1);
            $table->smallInteger('capacity');
            $table->string('capacity_reason', 500)->nullable();
            $table->foreignId('guide_account_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'school_year_id', 'grade', 'letter']);
        });

        DB::statement("ALTER TABLE sections ADD CONSTRAINT sections_grade_known CHECK (grade IN ({$grades}))");
        DB::statement("ALTER TABLE sections ADD CONSTRAINT sections_letter_valid CHECK (letter ~ '^[A-Z]$')");
        DB::statement('ALTER TABLE sections ADD CONSTRAINT sections_capacity_positive CHECK (capacity > 0)');

        Schema::create('curriculum_subjects', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 16);
            $table->foreignId('subject_id')->constrained();

            $table->unique(['institution_id', 'school_year_id', 'grade', 'subject_id']);
        });

        DB::statement("ALTER TABLE curriculum_subjects ADD CONSTRAINT curriculum_subjects_grade_known CHECK (grade IN ({$grades}))");

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('account_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One teacher per subject and section (EAC-R05.4).
            $table->unique(['section_id', 'subject_id']);
            // «Does this person teach this subject in this section?» (EAC-R08.4, NF02).
            $table->index(['account_id', 'section_id', 'subject_id']);
        });

        Schema::create('coordinator_assignments', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('users')->cascadeOnDelete();
            $table->string('level', 16);
            $table->string('grade', 16)->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'school_year_id']);
        });

        DB::statement("ALTER TABLE coordinator_assignments ADD CONSTRAINT coordinator_assignments_level_known CHECK (level IN ('inicial', 'primaria', 'media_general'))");
        DB::statement("ALTER TABLE coordinator_assignments ADD CONSTRAINT coordinator_assignments_grade_known CHECK (grade IS NULL OR grade IN ({$grades}))");
        // A NULL grade means the whole level; COALESCE makes it part of the key.
        DB::statement("CREATE UNIQUE INDEX coordinator_assignments_unique ON coordinator_assignments (school_year_id, account_id, level, COALESCE(grade, ''))");

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
