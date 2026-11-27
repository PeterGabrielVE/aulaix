<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/**
 * SPEC-015 RLT-R01.1 (ADR 0011): the request of an institution runs in one
 * transaction, opened before any middleware reads the database, and never
 * answers with a stream that would read outside it.
 */
test('the request transaction opens before authentication or route bindings read the database', function () {
    $priority = app(Kernel::class)->getMiddlewarePriority();
    $resolveTenant = array_search(ResolveTenant::class, $priority, true);

    expect($resolveTenant)->not->toBeFalse()
        ->and($resolveTenant)->toBeLessThan(array_search(AuthenticatesRequests::class, $priority, true))
        ->and($resolveTenant)->toBeLessThan(array_search(SubstituteBindings::class, $priority, true));
});

test('a request of an institution can not answer with a stream', function () {
    $institution = tenant();
    Route::domain('{tenant}.'.config('app.domain'))->middleware(['web', 'tenant'])
        ->get('/__stream', fn () => response()->stream(fn () => print ('late')));

    $this->withoutExceptionHandling()->get(tenantUrl($institution, '/__stream'));
})->throws(LogicException::class, 'A request of an institution can not answer with a stream.');
