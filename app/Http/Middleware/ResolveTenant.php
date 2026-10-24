<?php

namespace App\Http\Middleware;

use AulaX\Tenancy\Application\ResolveTenant\InstitutionNotFound;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantHandler;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantQuery;
use AulaX\Tenancy\Application\TenantActivator;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the institution for the current request from its subdomain
 * (F1-03), captured as the {tenant} segment of the domain route pattern
 * (see routes/web.php), and activates it as the tenant for the rest of the
 * request: current tenant, Row Level Security session (F1-02) and the
 * permission team (F1-06), all through the TenantActivator.
 *
 * An unknown, inactive or invalid subdomain is a 404 (InstitutionNotFound,
 * rendered by bootstrap/app.php).
 */
class ResolveTenant
{
    public function __construct(
        private readonly ResolveTenantHandler $resolveTenant,
        private readonly TenantActivator $tenantActivator,
    ) {}

    /**
     * @throws InstitutionNotFound
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->route('tenant');

        $this->tenantActivator->activate(($this->resolveTenant)(new ResolveTenantQuery($subdomain)));

        // Every route in the tenant domain group requires a {tenant} URL
        // parameter (it's part of the domain pattern). Binding it as a
        // default means route('login'), route('dashboard'), etc. "just
        // work" from controllers/views without threading it through by hand.
        URL::defaults(['tenant' => $subdomain]);

        // Route parameters are passed to controller actions positionally,
        // so leaving {tenant} in place would shift every other argument
        // (e.g. update(Request, User $user) would receive the subdomain
        // string as $user). It has been fully consumed at this point.
        $request->route()->forgetParameter('tenant');

        return $next($request);
    }
}
