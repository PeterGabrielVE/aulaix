<?php

use AulaX\IdentityAccess\Application\Port\InstitutionScope;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-015 RLT-R03.3: a console command that goes through several
 * institutions (identity:close-due-accounts, through InstitutionScope)
 * applies to each only its own Row Level Security, and leaves none behind.
 */
test('going through the institutions of an account applies to each only its own Row Level Security', function () {
    $account = User::factory()->create();
    $years = [];

    foreach (['norte', 'sur'] as $subdomain) {
        $institution = Institution::factory()->create(['subdomain' => $subdomain]);
        $years[$institution->id] = DB::transaction(function () use ($institution, $account) {
            DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $institution->id]);
            DB::table('institution_memberships')->insert(['user_id' => $account->id, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
        });
    }

    $seen = [];
    app(InstitutionScope::class)->forEachInstitutionOf($account->id, function (TenantContext $context) use (&$seen) {
        $seen[$context->institutionId->value] = [RowLevelSecurity::currentSetting(), DB::table('school_years')->pluck('id')->all()];
    });

    expect($seen)->toBe(collect($years)->map(fn (int $year, int $institution) => [(string) $institution, [$year]])->all())
        ->and(RowLevelSecurity::currentSetting())->toBe('')
        ->and(DB::table('school_years')->count())->toBe(0);
});
