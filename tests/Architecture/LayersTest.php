<?php

use App\Http\Controllers\AssignmentController;
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
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\EnrollmentChangeController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GeographyController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\GuardianPortalController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstitutionAssetController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InstitutionSettingsController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MyAssignmentsController;
use App\Http\Controllers\NextYearController;
use App\Http\Controllers\Platform\HomeController as PlatformHomeController;
use App\Http\Controllers\Platform\InvitationController as PlatformInvitationController;
use App\Http\Controllers\Platform\LoginController as PlatformLoginController;
use App\Http\Controllers\Platform\OperatorController as PlatformOperatorController;
use App\Http\Controllers\Platform\TotpController as PlatformTotpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\SchoolYearController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StructureController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsurePlatformOperator;
use App\Http\Middleware\RequireRecentTotp;
use App\Http\Middleware\RequireSuperAdmin;
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
    TransferController::class,
    GuardianPortalController::class,
    CorrectionController::class,
    // SPEC-008
    StructureController::class,
    SectionController::class,
    AssignmentController::class,
    MyAssignmentsController::class,
    // SPEC-010
    EnrollmentController::class,
    EnrollmentChangeController::class,
    PromotionController::class,
    NextYearController::class,
    // SPEC-014
    PlatformLoginController::class,
    PlatformInvitationController::class,
    PlatformHomeController::class,
    EnsurePlatformOperator::class,
    PlatformOperatorController::class,
    PlatformTotpController::class,
    RequireRecentTotp::class,
    RequireSuperAdmin::class,
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

test('no code sets the institution for a whole connection', function () {
    // SPEC-015 RLT-R01.2, ADR 0011: set_config(..., false) would outlive its
    // transaction and reach the next unit of work on a shared connection.
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src')) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && preg_match('/set_config\([^)]*,\s*false\s*\)/i', file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

test('no code activates an institution by hand; requests use forRequest() and switches within()', function () {
    // SPEC-015 RLT-R02, ADR 0011: activate() would hold the institution for
    // the rest of a transaction; a switch must give it back.
    $offenders = [];

    foreach (['src', 'app', 'database'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$directory)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || str_ends_with($file->getPathname(), 'PostgresTenantActivator.php')) {
                continue;
            }

            $code = file_get_contents($file->getPathname());

            if (str_contains($code, 'TenantActivator') && preg_match('/[aA]ctivator->(activate|deactivate)\(/', $code)) {
                $offenders[] = $file->getFilename();
            }
        }
    }

    // The queue listener only empties the tenant a job starts with (RLT-R03.1).
    expect($offenders)->toBe(['EnsureNoTenantOnJobStart.php']);
});

arch('only the activator writes the institution Row Level Security applies', function () {
    // ADR 0011: one place that switches, restores and checks.
    expect('AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity')->toOnlyBeUsedIn([
        'AulaX\Tenancy\Infrastructure\PostgresTenantActivator',
        // Migrations call its enable*() helpers, and the provider its SQL
        // default for institution_id; neither switches institution.
        'AulaX\Tenancy\Infrastructure\Persistence',
        'AulaX\Tenancy\Infrastructure\TenancyServiceProvider',
    ]);
});

arch('in Students, only the transfer copier acts as another institution', function () {
    // ADR 0008: switching institution inside a transaction is confined to
    // one adapter, so nothing else of the module writes as the destination.
    expect('AulaX\Students')
        ->not->toUse('AulaX\Tenancy\Application\TenantActivator')
        ->ignoring([
            'AulaX\Students\Infrastructure\TenantSwitchingTransferCopier',
            // Links a guardian in the institution of the accepted invitation, then restores the open one.
            'AulaX\Students\Application\GuardianAccount\LinkGuardianAccountHandler',
        ]);
});

arch('in Platform, only the bounded reader activates an institution', function () {
    // ADR 0010, SPEC-014 V1: one place, reviewed, always restored.
    expect('AulaX\Platform')
        ->not->toUse('AulaX\Tenancy\Application\TenantActivator')
        ->ignoring('AulaX\Platform\Infrastructure\TenantScopedReader');
});

arch('only the bounded reader opens a platform read, and only the aggregate queries ask for one', function () {
    // SPEC-014 V2.
    expect('AulaX\Shared\Application\PlatformReadScope')->toOnlyBeUsedIn([
        'AulaX\Platform\Infrastructure\TenantScopedReader',
        'AulaX\IdentityAccess\Application\Members\MemberCountsQuery',
        'AulaX\IdentityAccess\Application\Members\AdministratorContactsQuery',
        'AulaX\Students\Application\ActiveStudentCountQuery',
        'AulaX\Audit\Application\ListEntries\LastActivityQuery',
        'AulaX\Tenancy\Application\InstitutionFiles\UsedStorageQuery',
    ]);
});

arch('the aggregate queries serve the platform only', function (string $query) {
    // SPEC-014 V2: no screen of an institution, nor another module, reaches them.
    expect($query)->toOnlyBeUsedIn('AulaX\Platform\Infrastructure');
})->with([
    'AulaX\IdentityAccess\Application\Members\MemberCountsQuery',
    'AulaX\IdentityAccess\Application\Members\AdministratorContactsQuery',
    'AulaX\Students\Application\ActiveStudentCountQuery',
    'AulaX\Audit\Application\ListEntries\LastActivityQuery',
    'AulaX\Tenancy\Application\InstitutionFiles\UsedStorageQuery',
]);

arch('only the platform lists every institution and changes their status', function (string $useCase) {
    // SPEC-014 PLT-R04, R05: an institution never sees the others nor its own status switch.
    expect($useCase)->toOnlyBeUsedIn('AulaX\Platform');
})->with([
    'AulaX\Tenancy\Application\Administration\SetInstitutionStatusHandler',
    'AulaX\Tenancy\Application\Administration\ListAllInstitutionsQuery',
    'AulaX\Tenancy\Application\Administration\ShowInstitutionQuery',
]);

arch('the platform log is read only by the panel, and the panel never reads an institution log', function () {
    // SPEC-014 PLT-R07.2, R07.4 (AUD-R04.1).
    expect('AulaX\Audit\Application\PlatformLog\ListPlatformEntriesQuery')->toOnlyBeUsedIn('AulaX\Platform');
    expect('AulaX\Platform')->not->toUse([
        'AulaX\Audit\Application\ListEntries\ListAuditEntriesHandler',
        'AulaX\Audit\Application\ListEntries\AuditLog',
    ]);
});

arch('a consolidation case is resolved only from the console command or the panel', function () {
    // SPEC-014 PLT-R06.5, V3 (IDG-NF07): one use case, two inputs.
    expect('AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseHandler')->toOnlyBeUsedIn([
        'App\Console\Commands\ResolveConsolidationCase',
        'AulaX\Platform\Infrastructure\Consolidation\IdentityConsolidationDesk',
    ]);
});

arch('Workspace has no tables nor writes of its own', function () {
    // ADR 0009, SPEC-013 NAV-NF01: a composed view reads through the
    // Application layer of the modules that own the data.
    expect('AulaX\Workspace')->not->toUse(['Illuminate\Database', 'Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Schema']);
});

arch('the modules enrollments build on never depend on them', function () {
    // ADR 0012, SPEC-010: Enrollment reacts inside their writes through
    // their extension points; the dependency goes one way only.
    expect(['AulaX\Students', 'AulaX\AcademicStructure', 'AulaX\AcademicCalendar'])->not->toUse('AulaX\Enrollment');
});

arch('only the provider of Enrollment puts its reactions under the extension points', function () {
    // ADR 0012: the provider of the module that implements an extension
    // point is the only place that tags it.
    expect('AulaX\Enrollment\Application\Hooks')->toOnlyBeUsedIn('AulaX\Enrollment\Infrastructure\EnrollmentServiceProvider');
});

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
