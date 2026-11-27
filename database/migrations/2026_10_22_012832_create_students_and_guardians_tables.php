<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-009: students, their guardians, the links between them, their
 * withdrawals and their former cédulas escolares. Every table belongs to its
 * institution under Row Level Security (S-01).
 */
return new class extends Migration
{
    private const TABLES = ['student_previous_identifications', 'student_withdrawals', 'guardianships', 'guardians', 'students'];

    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->string('given_names', 120);
            $table->string('family_names', 120);
            $table->string('sex', 8);
            $table->date('birth_date');
            $table->char('nationality', 1);
            $table->string('identification_type', 16);
            $table->string('identification', 12);
            $table->char('birth_country', 2)->nullable();
            $table->foreignId('birth_state_id')->nullable()->constrained('states');
            $table->foreignId('parish_id')->nullable()->constrained('parishes');
            $table->string('address', 500)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->text('disability')->nullable();
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['institution_id', 'identification']);
            $table->index(['institution_id', 'status', 'family_names']);
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_sex_known CHECK (sex IN ('female', 'male'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_nationality_known CHECK (nationality IN ('V', 'E'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_identification_type_known CHECK (identification_type IN ('national_id', 'school_id'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_known CHECK (status IN ('active', 'withdrawn'))");

        Schema::create('student_previous_identifications', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('identification', 12);
            $table->date('replaced_on');

            $table->index(['institution_id', 'identification']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution(index: false);
            $table->string('given_names', 120);
            $table->string('family_names', 120);
            $table->string('document_type', 16);
            $table->string('document', 20);
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('account_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pending_invitation_id')->nullable()->constrained('invitations')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'document_type', 'document']);
        });

        DB::statement("ALTER TABLE guardians ADD CONSTRAINT guardians_document_type_known CHECK (document_type IN ('national_id', 'passport'))");
        DB::statement('CREATE UNIQUE INDEX guardians_one_per_account ON guardians (institution_id, account_id) WHERE account_id IS NOT NULL');

        Schema::create('guardianships', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->string('relationship', 16);
            $table->boolean('is_legal');
            $table->boolean('is_primary');

            $table->unique(['student_id', 'guardian_id']);
            $table->index('guardian_id');
        });

        DB::statement("ALTER TABLE guardianships ADD CONSTRAINT guardianships_relationship_known CHECK (relationship IN ('mother', 'father', 'other'))");
        // One primary contact per student (EST-R03.6).
        DB::statement('CREATE UNIQUE INDEX guardianships_one_primary ON guardianships (student_id) WHERE is_primary');

        Schema::create('student_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('withdrawn_on');
            $table->string('reason', 32);
            $table->string('note', 500)->nullable();
            $table->date('reinstated_on')->nullable();

            $table->index('student_id');
        });

        DB::statement("ALTER TABLE student_withdrawals ADD CONSTRAINT student_withdrawals_reason_known CHECK (reason IN ('moved', 'changed_school', 'economic_or_family', 'other'))");

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
