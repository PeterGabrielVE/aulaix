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
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Central domain (F1-07)
|--------------------------------------------------------------------------
|
| No subdomain, e.g. https://aulaix.test — the institution selector. Never
| passes through the ResolveTenant middleware, since there is no tenant yet.
*/
Route::domain(config('app.domain'))->group(function () {
    Route::get('/', fn () => Inertia::render('SelectInstitution'))->name('institutions.select');

    Route::get('/api/institutions/search', [InstitutionController::class, 'search'])
        ->middleware('throttle:30,1')
        ->name('institutions.search');

    // SPEC-004: one login for every institution (IDG-R03.1).
    Route::middleware('guest')->group(function () {
        Route::get('/login', [CentralLoginController::class, 'create'])->name('central.login');
        Route::post('/login', [CentralLoginController::class, 'store']);

        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('password.email');
        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('password.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/planteles', [InstitutionsController::class, 'show'])->name('central.home');
        Route::post('/planteles/{subdomain}', [InstitutionsController::class, 'open'])->name('central.open');
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('central.logout');

        // The account, managed only here (IDG-R03.1).
        Route::get('/cuenta', [AccountController::class, 'show'])->name('central.account');
        Route::patch('/cuenta/nombre', [AccountController::class, 'updateName'])->name('central.account.name');
        Route::post('/cuenta/eliminar/cancelar', [AccountController::class, 'cancelDeletion'])->name('central.account.deletion.cancel');
        Route::put('/cuenta/contrasena', [AccountController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('central.account.password');

        Route::get('/confirmar-contrasena', [ConfirmablePasswordController::class, 'show'])->name('central.password.confirm');
        Route::post('/confirmar-contrasena', [ConfirmablePasswordController::class, 'store'])->middleware('throttle:6,1');

        // Sensitive: the password again, typed less than 10 minutes ago (IDG-NF05).
        Route::middleware('password.confirm:central.password.confirm,600')->group(function () {
            Route::post('/cuenta/eliminar', [AccountController::class, 'requestDeletion'])->name('central.account.delete');
            Route::post('/cuenta/correo', [AccountController::class, 'requestEmailChange'])
                ->middleware('throttle:6,1')
                ->name('central.account.email');
            Route::get('/cuenta/unificar', [ConsolidationController::class, 'show'])->name('central.consolidation');
            Route::post('/cuenta/unificar/codigo', [ConsolidationController::class, 'sendCode'])
                ->middleware('throttle:3,1')
                ->name('central.consolidation.code');
            Route::post('/cuenta/unificar', [ConsolidationController::class, 'complete'])
                ->middleware('throttle:10,1')
                ->name('central.consolidation.complete');
        });
    });

    // The link sent to a new email address: it proves who receives mail
    // there, logged in or not (IDG-R05.3).
    Route::get('/cuenta/correo/{token}', [AccountController::class, 'confirmEmailChange'])
        ->middleware('throttle:6,1')
        ->name('central.account.email.confirm');

    // SPEC-004: accounts are global, so invitations are accepted here.
    Route::middleware('guest')->group(function () {
        Route::get('/invitaciones/{subdomain}/aceptada', [AcceptInvitationController::class, 'accepted'])
            ->name('invitations.accepted');
        Route::post('/invitaciones/{subdomain}', [AcceptInvitationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('invitations.store');
    });

    // With an account: logged in, from the link or the welcome screen (IDG-R04.5).
    Route::post('/invitaciones/{subdomain}/unirme', [AcceptInvitationController::class, 'join'])
        ->middleware('auth')
        ->name('invitations.join');

    // After the routes above, so its {token} never swallows "aceptada".
    Route::get('/invitaciones/{subdomain}/{token}', [AcceptInvitationController::class, 'create'])
        ->name('invitations.accept');
});

/*
| www.{APP_DOMAIN} is the central domain under another name, not an
| institution called "www" (a reserved subdomain, see Institution). Must be
| registered before the tenant group, whose {tenant} pattern matches it too.
*/
Route::domain('www.'.config('app.domain'))->group(function () {
    Route::get('/{path?}', fn (Request $request) => redirect()->away(
        $request->getScheme().'://'.config('app.domain').$request->getRequestUri(), 301
    ))->where('path', '.*');
});

/*
|--------------------------------------------------------------------------
| Tenant domain (F1-01 → F1-06)
|--------------------------------------------------------------------------
|
| e.g. https://demo.aulaix.test — everything institution-specific. The
| {tenant} segment is resolved into an Institution by the "tenant"
| middleware (App\Http\Middleware\ResolveTenant), which also sets up RLS
| and the roles/permissions team scope for the rest of the request.
*/
Route::domain('{tenant}.'.config('app.domain'))->middleware('tenant')->group(function () {
    // The central login hands the session over here (IDG-R03.7).
    Route::get('/auth/handoff', HandoffController::class)
        ->middleware('throttle:30,1')
        ->name('auth.handoff');

    Route::middleware(['auth', 'active', 'verified'])->group(function () {
        // Entry point after login: forwards to the user's role home.
        Route::get('/dashboard', [HomeController::class, 'redirect'])->name('dashboard');

        Route::get('/admin', [HomeController::class, 'admin'])
            ->middleware('role:Administrador')->name('admin.home');
        Route::get('/director', [HomeController::class, 'director'])
            ->middleware('role:Director')->name('director.home');
        Route::get('/coordinator', [HomeController::class, 'coordinator'])
            ->middleware('role:Coordinador')->name('coordinator.home');
        Route::get('/teacher', [HomeController::class, 'teacher'])
            ->middleware('role:Docente')->name('teacher.home');
        Route::get('/guardian', [HomeController::class, 'guardian'])
            ->middleware('role:Representante')->name('guardian.home');
        Route::get('/student', [HomeController::class, 'student'])
            ->middleware('role:Estudiante')->name('student.home');

        Route::middleware('permission:ver-bitacora')->group(function () {
            Route::get('/bitacora', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/bitacora/{entry}', [AuditLogController::class, 'show'])
                ->whereNumber('entry')
                ->name('audit.show');
        });

        Route::middleware('permission:gestionar-usuarios')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy'])
                ->whereNumber('user');

            Route::get('/invitations/{invitation}/edit', [InvitationController::class, 'edit'])
                ->whereNumber('invitation')->name('invitations.edit');
            Route::put('/invitations/{invitation}', [InvitationController::class, 'update'])
                ->whereNumber('invitation')->name('invitations.update');
            Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
                ->whereNumber('invitation')->name('invitations.resend');
            Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])
                ->whereNumber('invitation')->name('invitations.destroy');
        });
    });

    Route::middleware(['auth', 'active'])->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile/salir', [ProfileController::class, 'leave'])->name('profile.leave');
    });

    require __DIR__.'/auth.php';
});
