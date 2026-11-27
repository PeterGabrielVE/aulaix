<?php

namespace App\Http\Controllers;

use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use Illuminate\Http\JsonResponse;

/**
 * The location selectors of the institution form (SPEC-006 INS-R01.5):
 * municipalities of a state, parishes of a municipality.
 */
class GeographyController extends Controller
{
    public function municipalities(int $state, GeographyDirectory $geography): JsonResponse
    {
        return response()->json(['data' => $geography->municipalitiesOf($state)]);
    }

    public function parishes(int $municipality, GeographyDirectory $geography): JsonResponse
    {
        return response()->json(['data' => $geography->parishesOf($municipality)]);
    }
}
