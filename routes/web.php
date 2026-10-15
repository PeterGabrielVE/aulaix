<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AcceptInvitationController;
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

    // SPEC-004: accounts are global, so invitations are accepted here.
    Route::middleware('guest')->group(function () {
        Route::get('/invitaciones/{subdomain}/aceptada', [AcceptInvitationController::class, 'accepted'])
            ->name('invitations.accepted');
        Route::get('/invitaciones/{subdomain}/{token}', [AcceptInvitationController::class, 'create'])
            ->name('invitations.accept');
        Route::post('/invitaciones/{subdomain}', [AcceptInvitationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('invitations.store');
    });
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
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    require __DIR__.'/auth.php';
});
