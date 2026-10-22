<?php

namespace App\Http\Middleware;

use AulaX\Students\Application\GuardianPortal\IsGuardianQuery;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $institution = app(CurrentTenant::class)->context();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // SPEC-009 EST-R08.2: «Mis representados», by the account's link to a guardian here.
                'isGuardian' => fn () => $institution !== null && $request->user() !== null
                    && app(IsGuardianQuery::class)((int) $request->user()->id),
                'can' => [
                    'manageUsers' => (bool) $request->user()?->can('gestionar-usuarios'),
                    'viewAudit' => (bool) $request->user()?->can('ver-bitacora'),
                    'manageCalendar' => (bool) $request->user()?->can('gestionar-calendario'),
                    'viewInstitution' => (bool) $request->user()?->can('ver-plantel'),
                    'manageInstitution' => (bool) $request->user()?->can('gestionar-plantel'),
                    'viewStudents' => (bool) $request->user()?->can('ver-estudiantes'),
                    'manageStudents' => (bool) $request->user()?->can('gestionar-estudiantes'),
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'institution' => $institution ? [
                'name' => $institution->name,
                'subdomain' => $institution->subdomain,
                // Served to members only; the version changes with each logo (INS-R04.6).
                'logoUrl' => $institution->logoVersion === null ? null : route('institution.logo', ['v' => $institution->logoVersion]),
            ] : null,
            'appDomain' => config('app.domain'),
            // The central selector, where switching institution needs no password (IDG-R03.7).
            'switchInstitutionUrl' => $institution ? route('central.home') : null,
        ];
    }
}
