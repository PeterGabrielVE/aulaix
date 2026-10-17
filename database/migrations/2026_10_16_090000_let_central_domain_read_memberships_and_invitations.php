<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;

/**
 * ADR 0006 C (SPEC-004 PR B): the central login, the institution selector,
 * the handoff and the consolidation read a person's memberships and pending
 * invitations with no institution active. Reads open up like users' do;
 * writes stay limited to the active institution.
 */
return new class extends Migration
{
    private const TABLES = ['institution_memberships', 'invitations'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            RowLevelSecurity::enableWithCentralReads($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            RowLevelSecurity::disableCentralReads($table);
        }
    }
};
