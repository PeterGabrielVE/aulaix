<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
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
        $institution = app(CurrentTenant::class)->get();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'can' => [
                    'manageUsers' => (bool) $request->user()?->can('gestionar-usuarios'),
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'institution' => $institution ? [
                'name' => $institution->name,
                'subdomain' => $institution->subdomain,
            ] : null,
            'appDomain' => config('app.domain'),
        ];
    }
}
