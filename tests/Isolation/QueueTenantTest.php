<?php

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Tenancy\ProbeInstitutionJob;

/**
 * SPEC-015 RLT-R03.1, R03.2: a job starts with no institution, whatever the
 * previous one left; a job about an institution runs in it, and only once
 * the request that queued it has committed.
 */
beforeEach(function () {
    ProbeInstitutionJob::$runs = [];
    $this->norte = Institution::factory()->create(['subdomain' => 'norte']);
    DB::transaction(function () {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $this->norte->id]);
        DB::table('school_years')->insert(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
    });
});

test('a job starts with no institution, even when the previous one left a transaction open with one', function () {
    DB::beginTransaction();
    app(TenantActivator::class)->activate(TenantContext::of(InstitutionMapper::toDomain($this->norte)));

    event(new JobProcessing('redis', Mockery::mock(Job::class, ['payload' => []])->shouldIgnoreMissing()));

    expect(DB::transactionLevel())->toBe(0)
        ->and(RowLevelSecurity::currentSetting())->toBe('')
        ->and(app(CurrentTenant::class)->institutionId())->toBeNull();
});

test('a job about an institution runs in it, and leaves no institution after it', function () {
    ProbeInstitutionJob::dispatch($this->norte->id);

    expect(ProbeInstitutionJob::$runs)->toBe([['setting' => (string) $this->norte->id, 'tenant' => $this->norte->id, 'years' => 1, 'openTransactions' => 1]])
        ->and(RowLevelSecurity::currentSetting())->toBe('')
        ->and(app(CurrentTenant::class)->institutionId())->toBeNull();
});

test('a job queued by a request runs only after the request commits', function () {
    Route::domain('{tenant}.'.config('app.domain'))->middleware(['web', 'tenant'])
        ->get('/__isolation/queue', fn () => tap(response('queued'), fn () => ProbeInstitutionJob::dispatch($this->norte->id)));

    $this->get(tenantUrl($this->norte, '/__isolation/queue'))->assertOk();

    // It ran on its own transaction, opened by within() after the request's ended.
    expect(ProbeInstitutionJob::$runs)->toHaveCount(1)
        ->and(ProbeInstitutionJob::$runs[0]['openTransactions'])->toBe(1);
});
