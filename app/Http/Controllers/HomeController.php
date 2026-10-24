<?php

namespace App\Http\Controllers;

use App\Http\Navigation\HomePresenter;
use AulaX\IdentityAccess\Application\Home\ResolveHomeScreenHandler;
use AulaX\Workspace\Application\ShowHome\ShowHomeQuery;
use AulaX\Workspace\Domain\HomeKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly ShowHomeQuery $showHome) {}

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

    public function admin(Request $request): Response
    {
        return $this->home($request, HomeKind::Admin, 'Home/Admin');
    }

    public function director(Request $request): Response
    {
        return $this->home($request, HomeKind::Director, 'Home/Director');
    }

    public function coordinator(Request $request): Response
    {
        return $this->home($request, HomeKind::Coordinator, 'Home/Coordinator');
    }

    public function teacher(Request $request): Response
    {
        return $this->home($request, HomeKind::Teacher, 'Home/Teacher');
    }

    public function guardian(Request $request): Response
    {
        return $this->home($request, HomeKind::Guardian, 'Home/Guardian');
    }

    public function student(Request $request): Response
    {
        return $this->home($request, HomeKind::Student, 'Home/Student');
    }

    /**
     * SPEC-013: the screen's blocks, from Workspace (NAV-R04 – R09).
     */
    private function home(Request $request, HomeKind $kind, string $component): Response
    {
        return Inertia::render($component, [
            'home' => HomePresenter::present(($this->showHome)((int) $request->user()->getAuthIdentifier(), $kind)),
        ]);
    }
}
