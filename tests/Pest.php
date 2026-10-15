<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
|--------------------------------------------------------------------------
| Multi-tenancy helpers
|--------------------------------------------------------------------------
|
| Every tenant-scoped table (`users`, `password_reset_tokens`, ...) has PostgreSQL Row
| Level Security FORCE-enabled (F1-02), so inserting into it requires the
| same `app.current_institution_id` session variable that
| App\Http\Middleware\ResolveTenant sets on every real request. These
| helpers reproduce that setup for tests that create data directly via
| factories, before any HTTP request has run the middleware.
*/

function tenant(array $attributes = []): Institution
{
    $institution = Institution::factory()->create($attributes);

    // The same activation ResolveTenant runs on a real request: current
    // tenant, RLS session variable and permission team, all at once.
    app(TenantActivator::class)->activate(TenantContext::of(InstitutionMapper::toDomain($institution)));

    return $institution;
}

function tenantUrl(Institution $institution, string $path = '/'): string
{
    return 'http://'.$institution->subdomain.'.'.config('app.domain').$path;
}

function centralUrl(string $path = '/'): string
{
    return 'http://'.config('app.domain').$path;
}

/**
 * Make an existing institution the active tenant again, as a request to its
 * subdomain would.
 */
function useTenant(Institution $institution): void
{
    app(TenantActivator::class)->activate(TenantContext::of(InstitutionMapper::toDomain($institution)));
}

/**
 * What a request to the central domain starts from: no institution active,
 * no one logged in (sessions are per domain).
 */
function leaveTenant(): void
{
    app(TenantActivator::class)->deactivate();
    auth()->forgetGuards();
}

/**
 * A user of the given institution holding one of the roles seeded by
 * RolePermissionSeeder (seeded here on demand; it's idempotent).
 */
function userWithRole(Institution $institution, string $role, array $attributes = []): User
{
    RolePermissionSeeder::seedForInstitution($institution);

    $user = User::factory()->for($institution)->create($attributes);
    $user->assignRole($role);

    return $user;
}

/**
 * Make an existing account also a member of another institution (SPEC-004:
 * one person, one account, one membership per institution), optionally with
 * a base role there. Leaves that institution active.
 */
function joinInstitution(User $user, Institution $institution, ?string $role = null): User
{
    app(TenantActivator::class)->activate(TenantContext::of(InstitutionMapper::toDomain($institution)));

    DB::table('institution_memberships')->insert([
        'institution_id' => $institution->id,
        'user_id' => $user->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($role !== null) {
        RolePermissionSeeder::seedForInstitution($institution);
        $user->assignRole($role);
    }

    return $user;
}

/**
 * Serve the app from another base domain (e.g. the production one) for the
 * rest of the test. routes/web.php reads config('app.domain') when the
 * routes are registered, so they're registered again the way
 * bootstrap/app.php does it.
 */
function useAppDomain(string $domain): void
{
    config(['app.domain' => $domain]);

    $router = app('router');
    $router->setRoutes(new RouteCollection);
    $router->middleware('web')->group(base_path('routes/web.php'));

    // ->name() runs after a route is added, so the lookups need rebuilding.
    $router->getRoutes()->refreshNameLookups();
    $router->getRoutes()->refreshActionLookups();
    app('url')->setRoutes($router->getRoutes());
}
