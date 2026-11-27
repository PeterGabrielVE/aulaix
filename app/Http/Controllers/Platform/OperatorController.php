<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use AulaX\Platform\Application\Access\CurrentOperator;
use AulaX\Platform\Application\Operators\ChangeOperatorRoleHandler;
use AulaX\Platform\Application\Operators\InviteOperatorHandler;
use AulaX\Platform\Application\Operators\ListOperatorsQuery;
use AulaX\Platform\Application\Operators\ResetOperatorMfaHandler;
use AulaX\Platform\Application\Operators\SetOperatorActiveHandler;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform's operators (SPEC-014 PLT-R03), for SuperAdmins only (route
 * middleware, and again in the use cases); every change needs a fresh TOTP
 * (PLT-NF04).
 */
class OperatorController extends Controller
{
    public function index(ListOperatorsQuery $listOperators): Response
    {
        return Inertia::render('Platform/Operators', ['operators' => $listOperators()]);
    }

    public function store(Request $request, InviteOperatorHandler $invite): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(OperatorRole::class)],
        ]);

        $operator = $request->attributes->get('platform.operator');
        assert($operator instanceof CurrentOperator);

        $invite($data['name'], $data['email'], OperatorRole::from($data['role']), OperatorId::fromInt($operator->id));

        return back()->with('success', 'Se envió la invitación; vence en 48 horas.');
    }

    public function role(Request $request, int $operator, ChangeOperatorRoleHandler $changeRole): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(OperatorRole::class)]]);

        $changeRole(OperatorId::fromInt($operator), OperatorRole::from($data['role']));

        return back()->with('success', 'Se cambió el nivel del operador.');
    }

    public function active(Request $request, int $operator, SetOperatorActiveHandler $setActive): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        $setActive(OperatorId::fromInt($operator), (bool) $data['active']);

        return back()->with('success', $data['active'] ? 'Se reactivó el operador.' : 'Se desactivó el operador.');
    }

    public function resetMfa(int $operator, ResetOperatorMfaHandler $resetMfa): RedirectResponse
    {
        $resetMfa(OperatorId::fromInt($operator));

        return back()->with('success', 'Se restableció el segundo factor; el operador recibió un enlace para configurarlo.');
    }
}
