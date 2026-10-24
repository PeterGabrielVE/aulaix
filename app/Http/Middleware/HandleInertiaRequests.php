<?php

namespace App\Http\Middleware;

use App\Http\Navigation\MenuCatalog;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Workspace\Application\ShowLayout\LayoutView;
use AulaX\Workspace\Application\ShowLayout\ShowLayoutQuery;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private ?LayoutView $layoutView = null;

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
                'can' => [
                    'manageUsers' => (bool) $request->user()?->can('gestionar-usuarios'),
                    'viewAudit' => (bool) $request->user()?->can('ver-bitacora'),
                    'manageCalendar' => (bool) $request->user()?->can('gestionar-calendario'),
                    'viewInstitution' => (bool) $request->user()?->can('ver-plantel'),
                    'manageInstitution' => (bool) $request->user()?->can('gestionar-plantel'),
                    'viewStructure' => (bool) $request->user()?->can('ver-estructura'),
                    'manageStructure' => (bool) $request->user()?->can('gestionar-estructura'),
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
            // SPEC-013: menu, year and term, person and role, from Workspace.
            'layout' => fn () => $institution !== null && $request->user() !== null ? $this->layout($request) : null,
            // The central selector, where switching institution needs no password (IDG-R03.7),
            // offered to who has another institution (NAV-R02.5, R02.6).
            'switchInstitutionUrl' => fn () => $institution !== null && $request->user() !== null && $this->layoutView($request)->canSwitchInstitution
                ? route('central.home')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(Request $request): array
    {
        $layout = $this->layoutView($request);

        return [
            'navigation' => MenuCatalog::present($layout, $request),
            'calendar' => ['year' => $layout->yearName, 'term' => $layout->openTerm],
            'person' => ['name' => $request->user()->name, 'primaryRole' => $layout->primaryRole],
            'canSwitchInstitution' => $layout->canSwitchInstitution,
        ];
    }

    /**
     * Built once per request: the layout and the switch link share it.
     */
    private function layoutView(Request $request): LayoutView
    {
        return $this->layoutView ??= app(ShowLayoutQuery::class)((int) $request->user()->id);
    }
}
