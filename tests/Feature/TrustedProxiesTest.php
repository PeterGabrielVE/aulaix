<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * SPEC-005 (AUD-R01.3): behind the TLS proxy of the deployment, the client's
 * IP must come from X-Forwarded-For — but only when the request really comes
 * from a proxy listed in TRUSTED_PROXIES, or anyone could forge their IP.
 */
beforeEach(function () {
    Route::get('/_test/client-ip', fn (Request $request) => $request->ip());
});

it('uses the forwarded client IP when the request comes from a trusted proxy', function () {
    config(['app.trusted_proxies' => ['10.0.0.1']]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeader('X-Forwarded-For', '190.202.10.5')
        ->get('/_test/client-ip')
        ->assertSeeText('190.202.10.5');
});

it('ignores a forwarded IP sent by anyone else', function () {
    config(['app.trusted_proxies' => ['10.0.0.1']]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeader('X-Forwarded-For', '190.202.10.5')
        ->get('/_test/client-ip')
        ->assertSeeText('203.0.113.9');
});

it('trusts no proxy when none is configured', function () {
    config(['app.trusted_proxies' => []]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeader('X-Forwarded-For', '190.202.10.5')
        ->get('/_test/client-ip')
        ->assertSeeText('10.0.0.1');
});
