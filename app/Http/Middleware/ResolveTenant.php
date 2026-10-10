<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use App\Support\CurrentTenant;
use App\Support\RowLevelSecurity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the institution for the current request from its subdomain
 * (F1-03), captured as the {tenant} segment of the domain route pattern
 * (see routes/web.php). Beyond looking the institution up, this is what
 * turns "a subdomain" into "a tenant" for the rest of the request:
 *
 *  - CurrentTenant singleton, read by the BelongsToInstitution Eloquent scope
 *  - Postgres session variable, read by the Row Level Security policies (F1-02)
 *  - spatie/laravel-permission's team id, so roles/permissions are scoped
 *    to this institution (F1-06)
 */
class ResolveTenant
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->route('tenant');

        $institution = Institution::query()
            ->active()
            ->where('subdomain', $subdomain)
            ->first();

        abort_if($institution === null, 404, 'Institución no encontrada.');

        $this->currentTenant->set($institution);

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

        RowLevelSecurity::setInstitution($institution->id);

        $this->permissionRegistrar->setPermissionsTeamId($institution->id);

        return $next($request);
    }
}
