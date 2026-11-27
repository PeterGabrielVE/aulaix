<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Trusts the proxies listed in TRUSTED_PROXIES (config app.trusted_proxies),
 * read on each request so config:cache keeps working. Behind the deployment's
 * TLS proxy this makes $request->ip() the client's real IP (audit log,
 * SPEC-005; login throttling). With nothing configured, no proxy is trusted
 * and forwarded headers are ignored.
 */
class TrustConfiguredProxies extends TrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $proxies = config('app.trusted_proxies');

        return $proxies === [] ? null : $proxies;
    }
}
