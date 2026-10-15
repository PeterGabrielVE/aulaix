<?php

namespace App\Http\Controllers;

use AulaX\IdentityAccess\Application\Home\ResolveHomeScreenHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The "dashboard" route every login, verification and password flow
     * redirects to: forwards each user to their role's home screen.
     */
    public function redirect(Request $request, ResolveHomeScreenHandler $resolveHomeScreen): RedirectResponse|Response
    {
        $homeScreen = $resolveHomeScreen((int) $request->user()->getAuthIdentifier());

        return $homeScreen
            ? redirect()->route("{$homeScreen->value}.home")
            : Inertia::render('Home/NoRole');
    }

    public function admin(): Response
    {
        return Inertia::render('Home/Admin');
    }

    public function director(): Response
    {
        return Inertia::render('Home/Director');
    }

    public function coordinator(): Response
    {
        return Inertia::render('Home/Coordinator');
    }

    public function teacher(): Response
    {
        return Inertia::render('Home/Teacher');
    }

    public function guardian(): Response
    {
        return Inertia::render('Home/Guardian');
    }

    public function student(): Response
    {
        return Inertia::render('Home/Student');
    }
}
