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
use App\Http\Controllers\GradesController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\GuardianPortalController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstitutionAssetController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\InstitutionSettingsController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MarksController;
use App\Http\Controllers\MyAssignmentsController;
use App\Http\Controllers\NextYearController;
use App\Http\Controllers\Platform\ActivityController as PlatformActivityController;
use App\Http\Controllers\Platform\ConsolidationController as PlatformConsolidationController;
use App\Http\Controllers\Platform\HomeController as PlatformHomeController;
use App\Http\Controllers\Platform\InstitutionController as PlatformInstitutionController;
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
Route::domain('admin.'.config('app.domain'))->group(function () {
    // SPEC-014: the platform panel, with its own login and session (ADR 0010).
    // Registered before the tenant group, whose {tenant} pattern matches it too.
    Route::get('/login', [PlatformLoginController::class, 'create'])->name('platform.login');
    Route::post('/login', [PlatformLoginController::class, 'store'])->middleware('throttle:platform-login');

    Route::middleware('throttle:platform-links')->group(function () {
        Route::get('/invitacion/{token}', [PlatformInvitationController::class, 'show'])->name('platform.invitation.show');
        Route::post('/invitacion/{token}', [PlatformInvitationController::class, 'store'])->name('platform.invitation.store');
    });
    Route::get('/codigos-de-recuperacion', [PlatformInvitationController::class, 'recoveryCodes'])->name('platform.recovery-codes');

    Route::middleware('platform')->group(function () {
        Route::get('/', PlatformHomeController::class)->name('platform.home');
        Route::post('/logout', [PlatformLoginController::class, 'destroy'])->name('platform.logout');

        // PLT-NF04: a fresh TOTP before sensitive actions.
        Route::get('/confirmar-codigo', [PlatformTotpController::class, 'show'])->name('platform.totp.confirm');
        Route::post('/confirmar-codigo', [PlatformTotpController::class, 'store'])->middleware('throttle:5,1');
        Route::post('/codigos-de-recuperacion', [PlatformTotpController::class, 'regenerateRecoveryCodes'])
            ->middleware('platform.recent-totp')->name('platform.recovery-codes.regenerate');

        Route::whereNumber('institution')->group(function () {
            Route::get('/planteles', [PlatformInstitutionController::class, 'index'])->name('platform.institutions');
            Route::get('/planteles/{institution}', [PlatformInstitutionController::class, 'show'])->name('platform.institutions.show');
            Route::put('/planteles/{institution}/estado', [PlatformInstitutionController::class, 'active'])
                ->middleware(['platform.super', 'platform.recent-totp'])->name('platform.institutions.active');
        });

        Route::get('/bitacora', PlatformActivityController::class)->name('platform.activity');

        Route::whereNumber('account')->group(function () {
            Route::get('/consolidaciones', [PlatformConsolidationController::class, 'index'])->name('platform.consolidations');
            Route::get('/consolidaciones/{account}', [PlatformConsolidationController::class, 'show'])->name('platform.consolidations.show');
            Route::post('/consolidaciones/{account}/vista-previa', [PlatformConsolidationController::class, 'preview'])->name('platform.consolidations.preview');
            Route::post('/consolidaciones/{account}/resolver', [PlatformConsolidationController::class, 'resolve'])
                ->middleware('platform.recent-totp')->name('platform.consolidations.resolve');
        });

        Route::middleware('platform.super')->whereNumber('operator')->group(function () {
            Route::get('/operadores', [PlatformOperatorController::class, 'index'])->name('platform.operators');
            Route::middleware('platform.recent-totp')->group(function () {
                Route::post('/operadores', [PlatformOperatorController::class, 'store'])->name('platform.operators.store');
                Route::put('/operadores/{operator}/nivel', [PlatformOperatorController::class, 'role'])->name('platform.operators.role');
                Route::put('/operadores/{operator}/estado', [PlatformOperatorController::class, 'active'])->name('platform.operators.active');
                Route::post('/operadores/{operator}/restablecer-mfa', [PlatformOperatorController::class, 'resetMfa'])->name('platform.operators.reset-mfa');
            });
        });
    });
});

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

        // SPEC-009 EST-R08: a guardian's own students, by its account's link (no permission needed).
        Route::get('/mis-representados', [GuardianPortalController::class, 'index'])->name('my-students.index');
        Route::get('/mis-representados/{student}', [GuardianPortalController::class, 'show'])
            ->whereNumber('student')->name('my-students.show');
        Route::post('/mis-representados/correcciones', [CorrectionController::class, 'store'])
            ->middleware('throttle:10,1')->name('corrections.store');

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

        // SPEC-007: the school year and its terms.
        Route::middleware('permission:gestionar-calendario')->prefix('calendario')->group(function () {
            Route::get('/', [SchoolYearController::class, 'index'])->name('calendar.index');

            Route::middleware('throttle:30,1')->whereNumber(['year', 'term'])->group(function () {
                Route::post('/anios', [SchoolYearController::class, 'store'])->name('calendar.years.store');
                Route::put('/anios/{year}', [SchoolYearController::class, 'update'])->name('calendar.years.update');
                Route::delete('/anios/{year}', [SchoolYearController::class, 'destroy'])->name('calendar.years.destroy');
                Route::post('/anios/{year}/activar', [SchoolYearController::class, 'activate'])->name('calendar.years.activate');
                Route::post('/anios/{year}/cerrar', [SchoolYearController::class, 'close'])->name('calendar.years.close');
                Route::post('/anios/{year}/lapsos/cerrar', [TermController::class, 'close'])->name('calendar.terms.close');
                Route::post('/anios/{year}/lapsos/{term}/reabrir', [TermController::class, 'reopen'])->name('calendar.terms.reopen');
            });
        });

        // SPEC-006: the settings of the open institution.
        Route::prefix('plantel')->group(function () {
            Route::get('/', [InstitutionSettingsController::class, 'show'])
                ->middleware('permission:ver-plantel')->name('institution.show');
            Route::get('/logo', [InstitutionAssetController::class, 'logo'])->name('institution.logo');

            Route::middleware('permission:gestionar-plantel')->whereIn('role', ['director', 'secretary'])->group(function () {
                Route::put('/', [InstitutionSettingsController::class, 'update'])
                    ->middleware('throttle:30,1')->name('institution.update');
                Route::get('/firmas/{role}', [InstitutionAssetController::class, 'signature'])->name('institution.signatures.show');

                Route::middleware('throttle:10,1')->group(function () {
                    Route::post('/logo', [InstitutionAssetController::class, 'storeLogo'])->name('institution.logo.store');
                    Route::delete('/logo', [InstitutionAssetController::class, 'destroyLogo'])->name('institution.logo.destroy');
                    Route::post('/firmas/{role}', [InstitutionAssetController::class, 'storeSignature'])->name('institution.signatures.store');
                    Route::delete('/firmas/{role}', [InstitutionAssetController::class, 'destroySignature'])->name('institution.signatures.destroy');
                });
            });
        });

        Route::prefix('api/geografia')->whereNumber(['state', 'municipality'])->group(function () {
            Route::get('/estados/{state}/municipios', [GeographyController::class, 'municipalities'])->name('geography.municipalities');
            Route::get('/municipios/{municipality}/parroquias', [GeographyController::class, 'parishes'])->name('geography.parishes');
        });

        // SPEC-008: the structure of each school year.
        Route::get('/estructura', [StructureController::class, 'show'])
            ->middleware('permission:ver-estructura')->name('structure.show');
        Route::middleware(['permission:gestionar-estructura', 'throttle:60,1'])
            ->whereNumber(['year', 'section'])
            ->group(function () {
                Route::post('/estructura/{year}/secciones', [SectionController::class, 'store'])->name('structure.sections.store');
                Route::put('/estructura/secciones/{section}/cupo', [SectionController::class, 'capacity'])->name('structure.sections.capacity');
                Route::delete('/estructura/secciones/{section}', [SectionController::class, 'destroy'])->name('structure.sections.destroy');
                Route::put('/estructura/{year}/pensum/{grade}', [StructureController::class, 'curriculum'])
                    ->where('grade', '[a-z_0-9]+')->name('structure.curriculum');
                Route::post('/estructura/{year}/copiar', [StructureController::class, 'copy'])->name('structure.copy');
                Route::put('/estructura/secciones/{section}/materias/{subject}/docente', [AssignmentController::class, 'teacher'])
                    ->whereNumber('subject')->name('structure.teacher');
                Route::put('/estructura/secciones/{section}/guia', [AssignmentController::class, 'guide'])->name('structure.guide');
                Route::post('/estructura/{year}/coordinadores', [AssignmentController::class, 'coordinator'])->name('structure.coordinators.store');
                Route::delete('/estructura/{year}/coordinadores/{account}/{level}', [AssignmentController::class, 'removeCoordinator'])
                    ->whereNumber('account')->where('level', '[a-z_]+')->name('structure.coordinators.destroy');
            });
        // SPEC-010: the enrollments of each school year.
        Route::whereNumber(['year', 'section', 'enrollment'])->group(function () {
            Route::middleware('permission:ver-estudiantes')->group(function () {
                Route::get('/inscripciones', [EnrollmentController::class, 'index'])->name('enrollments.index');
                Route::get('/inscripciones/{year}/secciones/{section}', [EnrollmentController::class, 'section'])->name('enrollments.section');
            });
            Route::middleware(['permission:gestionar-inscripciones', 'throttle:60,1'])->group(function () {
                Route::post('/inscripciones', [EnrollmentController::class, 'store'])->name('enrollments.store');
                Route::post('/inscripciones/{enrollment}/seccion', [EnrollmentChangeController::class, 'section'])->name('enrollments.change-section');
                Route::post('/inscripciones/{enrollment}/anular', [EnrollmentChangeController::class, 'annul'])->name('enrollments.annul');
                // MAT-R06, R07: the results of the year and the next year's enrollment.
                Route::get('/prosecucion/{year}/secciones/{section}', [PromotionController::class, 'section'])->name('promotion.section');
                Route::put('/inscripciones/{enrollment}/resultado', [PromotionController::class, 'update'])->name('promotion.update');
                Route::get('/prosecucion/{year}/lote', [NextYearController::class, 'show'])->name('next-year.show');
                Route::post('/prosecucion/{year}/lote', [NextYearController::class, 'store'])->name('next-year.store');
            });
            Route::post('/inscripciones/{enrollment}/grado', [EnrollmentChangeController::class, 'grade'])
                ->middleware(['permission:aprobar-excepciones-inscripcion', 'throttle:60,1'])->name('enrollments.change-grade');
        });

        // SPEC-011: the plans and grades of Media General, the literals of Primaria and the reports of Inicial.
        Route::whereNumber(['year', 'section', 'subject', 'term', 'activity', 'student'])->group(function () {
            // As its menu entry (NAV-R03.3): whoever grades or manages students.
            Route::get('/notas', [GradesController::class, 'index'])
                ->middleware('permission:gestionar-calificaciones|gestionar-estudiantes')->name('grades.index');
            Route::get('/notas/{year}/secciones/{section}/materias/{subject}/lapsos/{term}', [GradesController::class, 'sheet'])
                ->middleware('permission:ver-calificaciones')->name('grades.sheet');
            Route::get('/notas/{year}/secciones/{section}/literales', [MarksController::class, 'show'])
                ->middleware('permission:ver-calificaciones')->name('grades.marks');
            Route::middleware(['permission:gestionar-calificaciones', 'throttle:60,1'])->group(function () {
                Route::put('/notas/{year}/secciones/{section}/materias/{subject}/lapsos/{term}/plan', [GradesController::class, 'plan'])->name('grades.plan');
                Route::put('/notas/actividades/{activity}', [GradesController::class, 'grades'])->name('grades.activity');
                Route::put('/notas/{year}/secciones/{section}/estudiantes/{student}', [MarksController::class, 'update'])->name('grades.marks.update');
            });
        });

        // SPEC-008 EAC-R08.2, for the teachers; SPEC-013 NAV-R03.3: as its menu entry.
        Route::get('/mis-asignaciones', MyAssignmentsController::class)->middleware('role:Docente')->name('my-assignments');

        // SPEC-009: the students and their guardians.
        Route::whereNumber(['student', 'guardian'])->group(function () {
            Route::middleware('permission:gestionar-estudiantes')->group(function () {
                Route::get('/estudiantes/nuevo', [StudentController::class, 'create'])->name('students.create');
                Route::get('/estudiantes/{student}/editar', [StudentController::class, 'edit'])->name('students.edit');
                Route::get('/representantes/buscar', [GuardianController::class, 'find'])
                    ->middleware('throttle:60,1')->name('guardians.find');

                Route::middleware('throttle:30,1')->group(function () {
                    Route::post('/estudiantes', [StudentController::class, 'store'])->name('students.store');
                    Route::put('/estudiantes/{student}', [StudentController::class, 'update'])->name('students.update');
                    Route::put('/estudiantes/{student}/salud', [StudentController::class, 'health'])->name('students.health');
                    Route::post('/estudiantes/{student}/retiro', [StudentController::class, 'withdraw'])->name('students.withdraw');
                    Route::post('/estudiantes/{student}/reincorporacion', [StudentController::class, 'reinstate'])->name('students.reinstate');
                    Route::put('/representantes/{guardian}', [GuardianController::class, 'update'])->name('guardians.update');
                    Route::post('/representantes/{guardian}/invitacion', [GuardianController::class, 'invite'])->name('guardians.invite');
                });

                Route::get('/correcciones', [CorrectionController::class, 'index'])->name('corrections.index');
                Route::post('/correcciones/{correction}/atendida', [CorrectionController::class, 'resolve'])
                    ->whereNumber('correction')->middleware('throttle:30,1')->name('corrections.resolve');

                // Transfers between institutions (ADR 0008).
                Route::get('/traslados', [TransferController::class, 'index'])->name('transfers.index');
                Route::post('/traslados', [TransferController::class, 'store'])
                    ->middleware('throttle:transfer-requests')->name('transfers.store');
                Route::middleware('throttle:30,1')->whereNumber('transfer')->group(function () {
                    Route::post('/traslados/{transfer}/aprobar', [TransferController::class, 'approve'])->name('transfers.approve');
                    Route::post('/traslados/{transfer}/rechazar', [TransferController::class, 'reject'])->name('transfers.reject');
                });
            });

            Route::middleware('permission:ver-estudiantes')->group(function () {
                Route::get('/estudiantes', [StudentController::class, 'index'])->name('students.index');
                Route::get('/estudiantes/{student}', [StudentController::class, 'show'])->name('students.show');
            });
        });

        Route::middleware('permission:ver-bitacora')->group(function () {
            Route::get('/bitacora', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/bitacora/{entry}', [AuditLogController::class, 'show'])
                ->whereNumber('entry')
                ->name('audit.show');
        });

        Route::middleware('permission:gestionar-usuarios')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy'])
                ->whereNumber('user');
            Route::put('/users/{user}/permiso-lapsos-cerrados', [UserController::class, 'setClosedTermGrades'])
                ->whereNumber('user')
                ->name('users.closed-term-grades');
            Route::put('/users/{user}/permiso-inscripciones', [UserController::class, 'setEnrollmentManagement'])
                ->whereNumber('user')
                ->name('users.enrollment-management');

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
