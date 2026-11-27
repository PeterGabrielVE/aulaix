<?php

use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-015 RLT-NF03: the transaction that holds a request's institution
 * (BEGIN, set_config, COMMIT) adds at most 5 ms to the p95 of a request.
 * Measured on real commits, outside any test transaction.
 */
pest()->group('perf');

/**
 * @param  callable(): mixed  $request
 * @return float The p95 of 200 runs, in milliseconds.
 */
function requestP95(callable $request): float
{
    $request();
    $timings = [];

    foreach (range(1, 200) as $run) {
        $began = hrtime(true);
        $request();
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    return $timings[(int) floor(count($timings) * 0.95)];
}

test('holding the institution in the request transaction costs under 5 ms (p95)', function () {
    $institution = TenantContext::of(InstitutionMapper::toDomain(Institution::factory()->create()));
    $work = fn () => DB::select('select count(*) from school_years');

    $bare = requestP95($work);
    $held = requestP95(fn () => app(TenantActivator::class)->forRequest($institution, $work, fn () => true));

    expect($held - $bare)->toBeLessThan(5.0);
});
