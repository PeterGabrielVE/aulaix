<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use AulaX\Platform\Application\Activity\ListPlatformActivityQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform log (SPEC-014 PLT-R07.2, R07.4), for every operator.
 */
class ActivityController extends Controller
{
    public function __invoke(Request $request, ListPlatformActivityQuery $listActivity): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'operator' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Platform/Activity', [
            'log' => $listActivity(
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $filters['operator'] ?? null,
                $filters['type'] ?? null,
                (int) ($filters['page'] ?? 1),
            ),
            'filters' => [
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'operator' => $filters['operator'] ?? '',
                'type' => $filters['type'] ?? '',
            ],
        ]);
    }
}
