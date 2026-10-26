<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The panel's landing page (SPEC-014); its sections arrive with PR B – D.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Platform/Home');
    }
}
