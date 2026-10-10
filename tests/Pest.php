<?php

use App\Models\Institution;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\RowLevelSecurity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Spatie\Permission\PermissionRegistrar;
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

    RowLevelSecurity::setInstitution($institution->id);

    app(CurrentTenant::class)->set($institution);
    app(PermissionRegistrar::class)->setPermissionsTeamId($institution->id);

    return $institution;
}

function tenantUrl(Institution $institution, string $path = '/'): string
{
    return 'http://'.$institution->subdomain.'.'.config('app.domain').$path;
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
