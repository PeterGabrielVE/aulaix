<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-016: the groups of pending subjects of each year (one per subject and
 * grade of origin, with their teacher), the revisions of failed final
 * grades, and plans that belong to a section or to a group (ADR 0013). The
 * members of a group are not stored: they follow from the results of the
 * year before (SPEC-010). Under Row Level Security (S-01).
 */
return new class extends Migration
{
    private const MEDIA_GRADES = "'media_1', 'media_2', 'media_3', 'media_4', 'media_5'";

    public function up(): void
    {
        $grades = self::MEDIA_GRADES;

        Schema::create('pending_groups', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('origin_grade', 16);
            // Like teaching_assignments (SPEC-008): a person who leaves is unassigned.
            $table->foreignId('teacher_account_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_year_id', 'subject_id', 'origin_grade']);
        });

        DB::statement("ALTER TABLE pending_groups ADD CONSTRAINT pending_groups_origin_grade_known CHECK (origin_grade IN ({$grades}))");

        // ADR 0013: a plan belongs to a section or to a group, exactly one.
        Schema::table('evaluation_plans', function (Blueprint $table) {
            $table->dropUnique('evaluation_plans_one_per_sheet');
            $table->foreignId('pending_group_id')->nullable()->after('section_id')->constrained('pending_groups')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE evaluation_plans ALTER COLUMN section_id DROP NOT NULL');
        DB::statement('ALTER TABLE evaluation_plans ADD CONSTRAINT evaluation_plans_one_owner CHECK (num_nonnulls(section_id, pending_group_id) = 1)');
        DB::statement('CREATE UNIQUE INDEX evaluation_plans_one_per_sheet ON evaluation_plans (school_year_id, section_id, subject_id, term) WHERE section_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX evaluation_plans_one_per_group_term ON evaluation_plans (pending_group_id, term) WHERE pending_group_id IS NOT NULL');

        Schema::create('grade_revisions', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('pending_group_id')->nullable()->constrained('pending_groups')->cascadeOnDelete();
            // Hundredths, like activity_grades.
            $table->unsignedInteger('score')->nullable();
            $table->boolean('not_presented')->default(false);
            $table->timestamps();

            $table->index('student_id');
        });

        DB::statement('ALTER TABLE grade_revisions ADD CONSTRAINT grade_revisions_one_owner CHECK (num_nonnulls(section_id, pending_group_id) = 1)');
        // "score IS NOT NULL": NULL BETWEEN … is NULL, and a CHECK lets NULL through.
        DB::statement('ALTER TABLE grade_revisions ADD CONSTRAINT grade_revisions_score_or_not_presented CHECK (
            (not_presented AND score IS NULL) OR (NOT not_presented AND score IS NOT NULL AND score BETWEEN 100 AND 2000)
        )');
        DB::statement('CREATE UNIQUE INDEX grade_revisions_one_per_section_subject ON grade_revisions (school_year_id, subject_id, student_id) WHERE section_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX grade_revisions_one_per_group_student ON grade_revisions (pending_group_id, student_id) WHERE pending_group_id IS NOT NULL');

        RowLevelSecurity::enable('pending_groups');
        RowLevelSecurity::enable('grade_revisions');
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_revisions');

        // The plans of groups go, with their activities and grades (CASCADE).
        DB::table('evaluation_plans')->whereNotNull('pending_group_id')->delete();
        DB::statement('DROP INDEX IF EXISTS evaluation_plans_one_per_group_term');
        DB::statement('DROP INDEX IF EXISTS evaluation_plans_one_per_sheet');
        DB::statement('ALTER TABLE evaluation_plans DROP CONSTRAINT IF EXISTS evaluation_plans_one_owner');

        Schema::table('evaluation_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_group_id');
        });

        DB::statement('ALTER TABLE evaluation_plans ALTER COLUMN section_id SET NOT NULL');

        Schema::table('evaluation_plans', function (Blueprint $table) {
            $table->unique(['school_year_id', 'section_id', 'subject_id', 'term'], 'evaluation_plans_one_per_sheet');
        });

        Schema::dropIfExists('pending_groups');
    }
};
