<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use AulaX\Platform\Application\Institutions\ListInstitutionsQuery;
use AulaX\Platform\Application\Institutions\SetInstitutionActiveHandler;
use AulaX\Platform\Application\Institutions\ShowInstitutionUsageQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The institutions and their usage (SPEC-014 PLT-R04), for every operator;
 * switching one off or on (PLT-R05) is for SuperAdmins with a fresh TOTP
 * (route middleware, and again in the use case).
 */
class InstitutionController extends Controller
{
    public function index(Request $request, ListInstitutionsQuery $listInstitutions): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Platform/Institutions', [
            'institutions' => $listInstitutions($filters['search'] ?? null, $filters['status'] ?? null, (int) ($filters['page'] ?? 1)),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function show(int $institution, ShowInstitutionUsageQuery $showUsage): Response
    {
        return Inertia::render('Platform/Institution', $showUsage($institution));
    }

    public function active(Request $request, int $institution, SetInstitutionActiveHandler $setActive): RedirectResponse
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $setActive($institution, (bool) $data['active'], (string) ($data['reason'] ?? ''));

        return back()->with('success', $data['active'] ? 'Se reactivó el plantel y se avisó a sus Administradores.' : 'Se desactivó el plantel y se avisó a sus Administradores.');
    }
}
