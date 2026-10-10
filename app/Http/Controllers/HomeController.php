<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The "dashboard" route every login, verification and password flow
     * redirects to: forwards each user to their role's home screen
     * (User::HOME_ROUTES).
     */
    public function redirect(Request $request): RedirectResponse|Response
    {
        $route = $request->user()->homeRoute();

        return $route
            ? redirect()->route($route)
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
