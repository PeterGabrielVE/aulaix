<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel keys password_reset_tokens by email alone, but the same email
     * can belong to a different user in each institution — so a reset (or
     * invitation) requested in one institution silently replaced the
     * pending token of the other.
     *
     * Rather than overriding the framework's token repository, the table
     * gets an institution_id that defaults to the RLS session variable
     * ResolveTenant sets on every tenant request, plus the same
     * tenant_isolation policy as `users`. Laravel's unscoped
     * `where('email', ...)` queries then only ever see the current
     * institution's tokens, enforced by Postgres.
     *
     * Tokens are short-lived, so the table is rebuilt rather than migrated.
     */
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->foreignId('institution_id')
                ->default(DB::raw("nullif(current_setting('app.current_institution_id', true), '')::bigint"))
                ->constrained()
                ->cascadeOnDelete();
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->primary(['institution_id', 'email']);
        });

        DB::statement('ALTER TABLE password_reset_tokens ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE password_reset_tokens FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON password_reset_tokens
            USING (institution_id = current_setting('app.current_institution_id', true)::bigint)
            WITH CHECK (institution_id = current_setting('app.current_institution_id', true)::bigint)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
