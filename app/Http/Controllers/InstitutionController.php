<?php

namespace App\Http\Controllers;

use AulaX\Tenancy\Application\SearchInstitutions\SearchInstitutionsHandler;
use AulaX\Tenancy\Application\SearchInstitutions\SearchInstitutionsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    /**
     * Public search used by the central selector (F1-07) to help a user
     * find their institution's subdomain before logging in. Rate limited in
     * routes/web.php (TEN-R04.5).
     */
    public function search(Request $request, SearchInstitutionsHandler $searchInstitutions): JsonResponse
    {
        $institutions = $searchInstitutions(new SearchInstitutionsQuery((string) $request->query('q', '')));

        return response()->json(['data' => $institutions]);
    }
}
