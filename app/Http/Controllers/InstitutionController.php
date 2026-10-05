<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    /**
     * Public search used by the central selector (F1-07) to help a user
     * find their institution's subdomain before logging in.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $institutions = Institution::query()
            ->where('status', 'active')
            ->when($query !== '', fn ($builder) => $builder->where('name', 'ilike', "%{$query}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['name', 'subdomain']);

        return response()->json(['data' => $institutions]);
    }
}
