<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-007: the school years of each institution and their three terms.
 * Besides the usual isolation (S-01), the database keeps the rules across
 * years and terms on its own (CAL-NF02, ADR 0007).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Trusted extension since PostgreSQL 13: the app role creates it as
        // the database owner, no superuser needed (S-03, ADR 0007).
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->belongsToInstitution();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE school_years ADD CONSTRAINT school_years_dates_in_order CHECK (ends_on > starts_on)');
        DB::statement("ALTER TABLE school_years ADD CONSTRAINT school_years_status_known CHECK (status IN ('planned', 'active', 'closed'))");
        DB::statement("CREATE UNIQUE INDEX school_years_one_active ON school_years (institution_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX school_years_one_planned ON school_years (institution_id) WHERE status = 'planned'");
        DB::statement("ALTER TABLE school_years ADD CONSTRAINT school_years_no_overlap EXCLUDE USING gist (institution_id WITH =, daterange(starts_on, ends_on, '[]') WITH &&)");
        RowLevelSecurity::enable('school_years');

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            // Repeats the year's, so Row Level Security stays the standard one.
            $table->belongsToInstitution(index: false);
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['school_year_id', 'number']);
        });

        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_number_range CHECK (number BETWEEN 1 AND 3)');
        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_dates_in_order CHECK (ends_on >= starts_on)');
        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_status_known CHECK (status IN ('pending', 'open', 'closed'))");
        DB::statement("CREATE UNIQUE INDEX terms_one_open ON terms (school_year_id) WHERE status = 'open'");
        RowLevelSecurity::enable('terms');
    }

    /**
     * The btree_gist extension stays: other tables may come to use it.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('school_years');
    }
};
