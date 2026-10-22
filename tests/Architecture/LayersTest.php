<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\HandoffController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Central\AccountController;
use App\Http\Controllers\Central\ConsolidationController;
use App\Http\Controllers\Central\InstitutionsController;
use App\Http\Controllers\Central\LoginController as CentralLoginController;
use App\Http\Controllers\GeographyController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstitutionAssetController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InstitutionSettingsController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchoolYearController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\ResolveTenant;

/**
 * SPEC-000 (FND-R02): the dependency rules of specs/constitution.md
 * (A-01 – A-06) as executable checks. Modules are discovered from src/, so a
 * new module is covered without touching this file.
 */
const FRAMEWORK_NAMESPACES = ['Illuminate', 'Spatie', 'Inertia', 'App'];

/**
 * Global helpers that reach into the framework container or clock.
 */
const FRAMEWORK_HELPERS = ['now', 'today', 'config', 'app', 'resolve', '__', 'trans', 'request', 'auth', 'session', 'event', 'dispatch'];

/**
 * Controllers already moved onto use cases (FND-R02.6). Each refactor spec
 * adds its controllers here, which forbids them from touching Eloquent or
 * any module's infrastructure from then on.
 *
 * @var list<class-string>
 */
const MIGRATED_CONTROLLERS = [
    // SPEC-001
    ResolveTenant::class,
    InstitutionController::class,
    // SPEC-005
    AuditLogController::class,
    // SPEC-004
    UserController::class,
    InvitationController::class,
    AcceptInvitationController::class,
    HomeController::class,
    EnsureActiveMembership::class,
    // SPEC-004 PR B
    CentralLoginController::class,
    InstitutionsController::class,
    HandoffController::class,
    AuthenticatedSessionController::class,
    ConsolidationController::class,
    ConfirmablePasswordController::class,
    PasswordResetLinkController::class,
    NewPasswordController::class,
    // SPEC-004 PR C
    AccountController::class,
    ProfileController::class,
    // SPEC-007
    SchoolYearController::class,
    TermController::class,
    // SPEC-006
    InstitutionSettingsController::class,
    InstitutionAssetController::class,
    GeographyController::class,
    // SPEC-009
    StudentController::class,
    GuardianController::class,
];

/**
 * The only links allowed between two modules' infrastructure, each one
 * justified in its spec. Everything else stays forbidden (FND-R02.4).
 *
 * @var array<string, list<class-string>>
 */
const ALLOWED_INFRASTRUCTURE_LINKS = [
    // SPEC-001: an institution is located in a parish (Eloquent relation).
    'Tenancy' => ['AulaX\Catalogs\Infrastructure\Persistence\ParishModel'],
];

/**
 * @return list<string>
 */
function modules(): array
{
    $directories = glob(dirname(__DIR__, 2).'/src/*', GLOB_ONLYDIR) ?: [];

    return array_map('basename', $directories);
}

/**
 * @return list<string>
 */
function layerNamespaces(string $layer): array
{
    return array_values(array_filter(
        array_map(fn (string $module) => "AulaX\\{$module}\\{$layer}", modules()),
        fn (string $namespace) => is_dir(dirname(__DIR__, 2).'/src/'.str_replace(['AulaX\\', '\\'], ['', '/'], $namespace)),
    ));
}

dataset('modules', fn () => modules());
dataset('domain layers', fn () => layerNamespaces('Domain'));
dataset('application layers', fn () => layerNamespaces('Application'));
dataset('core layers', fn () => [...layerNamespaces('Domain'), ...layerNamespaces('Application')]);

arch('a domain layer does not depend on the framework', function (string $layer) {
    expect($layer)
        ->not->toUse([...FRAMEWORK_NAMESPACES, 'Carbon'])
        ->not->toUse(FRAMEWORK_HELPERS);
})->with('domain layers');

arch('an application layer does not depend on the framework', function (string $layer) {
    expect($layer)
        ->not->toUse(FRAMEWORK_NAMESPACES)
        ->not->toUse(FRAMEWORK_HELPERS);
})->with('application layers');

arch('the core never reaches into infrastructure', function (string $layer) {
    expect($layer)->not->toUse(layerNamespaces('Infrastructure'));
})->with('core layers');

arch('a module only uses the domain and infrastructure of Shared', function (string $module) {
    $foreignInternals = collect(modules())
        ->reject(fn (string $other) => in_array($other, [$module, 'Shared'], true))
        ->flatMap(fn (string $other) => ["AulaX\\{$other}\\Domain", "AulaX\\{$other}\\Infrastructure"])
        ->all();

    if ($foreignInternals === []) {
        $this->markTestSkipped("{$module} is the only module besides Shared.");
    }

    expect("AulaX\\{$module}")
        ->not->toUse($foreignInternals)
        ->ignoring(ALLOWED_INFRASTRUCTURE_LINKS[$module] ?? []);
})->with('modules');

arch('Shared depends on no other module', function () {
    $otherModules = collect(modules())
        ->reject(fn (string $module) => $module === 'Shared')
        ->map(fn (string $module) => "AulaX\\{$module}")
        ->all();

    if ($otherModules === []) {
        $this->markTestSkipped('Shared is the only module.');
    }

    expect('AulaX\Shared')->not->toUse($otherModules);
});

arch('only IdentityAccess uses the account model', function (string $module) {
    // With no institution active, Row Level Security shows every account
    // (ADR 0006): reading them is IdentityAccess's job alone.
    if ($module === 'IdentityAccess') {
        $this->markTestSkipped('The account model belongs to IdentityAccess.');
    }

    expect("AulaX\\{$module}")->not->toUse('AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel');
})->with('modules');

arch('every file in src declares strict types')
    ->expect('AulaX')
    ->toUseStrictTypes();

arch('a migrated controller does not use Eloquent or infrastructure directly', function (string $controller) {
    // An arch expectation on a class that does not exist passes vacuously.
    expect(class_exists($controller))->toBeTrue("{$controller} does not exist.");

    expect($controller)->not->toUse([
        'App\Models',
        'Illuminate\Database',
        'Illuminate\Support\Facades\DB',
        ...layerNamespaces('Infrastructure'),
    ]);
})->with(MIGRATED_CONTROLLERS ?: [null])->skip(MIGRATED_CONTROLLERS === [], 'No controller has been migrated to use cases yet.');
