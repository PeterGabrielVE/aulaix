<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use AulaX\IdentityAccess\Application\Members\InvitationView;
use AulaX\IdentityAccess\Application\Members\InviteMemberCommand;
use AulaX\IdentityAccess\Application\Members\InviteMemberHandler;
use AulaX\IdentityAccess\Application\Members\ListMembersHandler;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\IdentityAccess\Application\Members\MemberView;
use AulaX\IdentityAccess\Application\Members\UpdateMemberCommand;
use AulaX\IdentityAccess\Application\Members\UpdateMemberHandler;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Usuarios" screens of roles with gestionar-usuarios (Administrador,
 * Director): the institution's members and its pending invitations
 * (SPEC-004). There is no public registration: people join through an
 * invitation. A member's name and email belong to them; managers only change
 * their roles and whether their membership is active (IDG-R05.5).
 */
class UserController extends Controller
{
    public function index(Request $request, ListMembersHandler $listMembers): Response
    {
        $search = trim((string) $request->query('search', ''));
        $result = $listMembers($search, (int) $request->query('page', 1));
        $members = $result['members'];

        return Inertia::render('Users/Index', [
            'users' => [
                'data' => array_map(fn (MemberView $member) => self::member($member), $members->members),
                'total' => $members->total,
                'page' => $members->page,
                'lastPage' => max(1, (int) ceil($members->total / $members->perPage)),
            ],
            'invitations' => array_map(fn (InvitationView $invitation) => self::invitation($invitation), $result['invitations']),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(RoleCatalog $roles): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => $roles->available()->inPriorityOrder(),
        ]);
    }

    public function store(InviteMemberRequest $request, InviteMemberHandler $inviteMember): RedirectResponse
    {
        $inviteMember(new InviteMemberCommand(
            $request->user()->id,
            $request->validated('email'),
            $request->validated('name'),
            $request->validated('roles'),
        ));

        return redirect()->route('users.index')
            ->with('success', "Se envió una invitación a {$request->validated('email')}.");
    }

    public function edit(int $user, MemberDirectory $directory, RoleCatalog $roles): Response
    {
        $member = $directory->member($user);

        abort_if($member === null, 404);

        return Inertia::render('Users/Edit', [
            'user' => self::member($member),
            'roles' => $roles->available()->inPriorityOrder(),
        ]);
    }

    public function update(UpdateMemberRequest $request, int $user, UpdateMemberHandler $updateMember): RedirectResponse
    {
        $updateMember(new UpdateMemberCommand(
            $request->user()->id,
            $user,
            $request->validated('roles'),
            $request->validated('status') === 'active',
        ));

        return redirect()->route('users.index')->with('success', 'Se guardaron los cambios.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function member(MemberView $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'roles' => $member->roles,
            'status' => $member->active ? 'active' : 'inactive',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invitation(InvitationView $invitation): array
    {
        return [
            'id' => $invitation->id,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'roles' => $invitation->roles,
            'expiresAt' => $invitation->expiresAt->setTimezone(new DateTimeZone('America/Caracas'))->format('d/m/Y'),
            'expired' => $invitation->expiresAt < now()->toDateTimeImmutable(),
        ];
    }
}
