<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * User management for roles with gestionar-usuarios (Administrador,
 * Director). There is no public registration:
 * every account is created here and activated through an emailed
 * invitation (App\Notifications\UserInvitation).
 *
 * Users are resolved through the tenant-scoped User model (Eloquent scope +
 * RLS), so {user} from another institution is simply a 404.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => UserRequest::assignableRoles(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            // Never shown to anyone; replaced when the invitation is accepted.
            'password' => Str::password(32),
            'status' => UserStatus::Active,
        ]);

        $user->syncRoles($request->roles);
        $user->sendInvitation();

        return redirect()->route('users.index')
            ->with('success', "Se envió una invitación a {$user->email}.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $this->present($user->load('roles:id,name')),
            'roles' => UserRequest::assignableRoles(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->only('name', 'email', 'status'));

        // The invitation (and verification) was tied to the old address.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->syncRoles($request->roles);

        return redirect()->route('users.index')
            ->with('success', "Se actualizó a {$user->name}.");
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        abort_if($user->hasAcceptedInvitation(), 409, 'Este usuario ya activó su cuenta.');

        $user->sendInvitation();

        return back()->with('success', "Se reenvió la invitación a {$user->email}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            // In the same priority order as User::HOME_ROUTES.
            'roles' => array_values(array_intersect(
                array_keys(User::HOME_ROUTES),
                $user->roles->pluck('name')->all(),
            )),
            'invitation_pending' => ! $user->hasAcceptedInvitation(),
        ];
    }
}
