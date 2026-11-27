<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-015: tests of the database isolation between units of work. They
 * run without the transaction RefreshDatabase wraps every other test in,
 * which would hide exactly the leaks they look for: what they write is
 * really committed, and removed again afterwards. The audit log is
 * immutable, so they must not write to it.
 */
abstract class IsolationTestCase extends TestCase
{
    /**
     * Tenant tables these tests write, children first.
     *
     * @var list<string>
     */
    private const TENANT_TABLES = ['enrollments', 'sections', 'students', 'terms', 'school_years', 'institution_memberships'];

    private int $lastInstitutionBefore = 0;

    private int $lastAccountBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = true;
        }

        $this->lastInstitutionBefore = (int) DB::table('institutions')->max('id');
        $this->lastAccountBefore = (int) DB::table('users')->max('id');
    }

    protected function tearDown(): void
    {
        // A test that failed half way may leave a transaction open.
        if (DB::transactionLevel() > 0) {
            DB::rollBack(0);
        }

        $created = DB::table('institutions')->where('id', '>', $this->lastInstitutionBefore)->pluck('id');

        foreach ($created as $institutionId) {
            DB::transaction(function () use ($institutionId) {
                DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $institutionId]);

                foreach (self::TENANT_TABLES as $table) {
                    DB::table($table)->delete();
                }
            });
        }

        DB::table('institutions')->whereIn('id', $created)->delete();
        DB::table('users')->where('id', '>', $this->lastAccountBefore)->delete();

        parent::tearDown();
    }
}
