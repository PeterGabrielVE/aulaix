<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\InvitationMail;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\IdentityAccess\Infrastructure\Persistence\InvitationModel;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * SPEC-002 IAM-R09/R10 as rewritten by SPEC-004: managers invite people and
 * manage their memberships; the account itself belongs to its owner.
 */
function invite(User $manager, Institution $institution, string $email, array $roles = ['Docente'], string $name = 'Persona Invitada'): InvitationModel
{
    test()->actingAs($manager)->post(tenantUrl($institution, '/users'), [
        'name' => $name, 'email' => $email, 'roles' => $roles,
    ])->assertSessionHasNoErrors();

    return InvitationModel::query()->where('email', $email)->where('status', 'pending')->sole();
}

function membershipStatus(User $user): ?string
{
    return DB::table('institution_memberships')->where('user_id', $user->id)->value('status');
}

test('administrators can list the users of their institution', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    userWithRole($institution, 'Docente');

    $response = $this->actingAs($admin)->get(tenantUrl($institution, '/users'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Users/Index')
        ->has('users.data', 2)
    );
});

test('the user list never includes users of another institution', function () {
    $institutionA = tenant();
    User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    $admin = userWithRole($institutionB, 'Administrador');

    $response = $this->actingAs($admin)->get(tenantUrl($institutionB, '/users'));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.id', $admin->id)
    );
});

test('users without the gestionar-usuarios permission can not manage users', function (string $role) {
    $institution = tenant();
    $member = userWithRole($institution, $role);

    $this->actingAs($member)->get(tenantUrl($institution, '/users'))->assertForbidden();
    $this->actingAs($member)->get(tenantUrl($institution, '/users/create'))->assertForbidden();
    $this->actingAs($member)->post(tenantUrl($institution, '/users'), [
        'name' => 'Nuevo', 'email' => 'nuevo@example.com', 'roles' => ['Administrador'],
    ])->assertForbidden();
})->with(['Coordinador', 'Docente', 'Representante', 'Estudiante']);

test('a director can manage the users of their institution', function () {
    Notification::fake();

    $institution = tenant();
    $director = userWithRole($institution, 'Director');

    $this->actingAs($director)->get(tenantUrl($institution, '/users'))->assertOk();

    expect(invite($director, $institution, 'luis@example.com', ['Coordinador'])->roles)->toBe(['Coordinador']);
});

test('a director can not make anyone an administrador', function () {
    Notification::fake();

    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $teacher = userWithRole($institution, 'Docente');

    $this->actingAs($director)->post(tenantUrl($institution, '/users'), [
        'name' => 'Nuevo', 'email' => 'nuevo@example.com', 'roles' => ['Administrador'],
    ])->assertSessionHasErrors(['roles' => 'Solo un Administrador puede asignar o quitar el rol de Administrador.']);

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$director->id}"), [
        'roles' => ['Director', 'Administrador'], 'status' => 'active',
    ])->assertSessionHasErrors('roles');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$teacher->id}"), [
        'roles' => ['Administrador'], 'status' => 'active',
    ])->assertSessionHasErrors('roles');

    expect(InvitationModel::query()->where('email', 'nuevo@example.com')->exists())->toBeFalse();
    expect($director->fresh()->hasRole('Administrador'))->toBeFalse();
    expect($teacher->fresh()->hasRole('Administrador'))->toBeFalse();
});

test('a director can not take the administrador role away', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'roles' => ['Docente'], 'status' => 'active',
    ])->assertSessionHasErrors('roles');

    expect($admin->fresh()->hasRole('Administrador'))->toBeTrue();
});

test('a director can edit an administrador without touching that role', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'roles' => ['Administrador', 'Docente'], 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($admin->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Administrador', 'Docente']);
});

test('a director can not give up managing users', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$director->id}"), [
        'roles' => ['Coordinador'], 'status' => 'active',
    ])->assertSessionHasErrors(['roles' => 'No puedes quitarte a ti mismo la gestión de usuarios.']);

    expect($director->fresh()->hasRole('Director'))->toBeTrue();
});

test('an administrador can not swap their own role for director', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    // Director also manages users, but can't grant Administrador back.
    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'roles' => ['Director'], 'status' => 'active',
    ])->assertSessionHasErrors(['roles' => 'No puedes quitarte a ti mismo el rol de Administrador.']);

    expect($admin->fresh()->getRoleNames()->all())->toBe(['Administrador']);
});

test('an administrador can add roles to themselves', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'roles' => ['Administrador', 'Representante'], 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($admin->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Administrador', 'Representante']);
});

test('a user can be given several roles in the same institution', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    invite($admin, $institution, 'ana@example.com', ['Representante', 'Docente'], 'Ana Díaz');

    $this->actingAs($admin)->get(tenantUrl($institution, '/users'))
        ->assertInertia(fn ($page) => $page->where('invitations.0.roles', ['Docente', 'Representante']));
});

test('at least one role is required', function (array $payload) {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'X', 'email' => 'x@example.com', ...$payload,
    ])->assertSessionHasErrors('roles');
})->with([
    'missing' => [[]],
    'empty' => [['roles' => []]],
    'not a list' => [['roles' => 'Docente']],
]);

test('guests are sent to the login screen', function () {
    $institution = tenant();

    $this->get(tenantUrl($institution, '/users'))->assertRedirect(route('login'));
});

test('an administrator invites a person with a role and the link is emailed', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'María Pérez',
        'email' => ' Maria@Example.com ',
        'roles' => ['Docente'],
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $invitation = InvitationModel::query()->sole();
    expect($invitation->institution_id)->toBe($institution->id)
        ->and($invitation->email)->toBe('maria@example.com')
        ->and($invitation->roles)->toBe(['Docente'])
        ->and($invitation->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString());

    // Nobody gets an account until they accept.
    expect(User::query()->where('email', 'maria@example.com')->exists())->toBeFalse();

    Notification::assertSentOnDemand(InvitationMail::class, function (InvitationMail $mail, array $channels, object $notifiable) use ($institution) {
        return $notifiable->routes['mail'] === 'maria@example.com'
            && str_starts_with($mail->url, centralUrl("/invitaciones/{$institution->subdomain}/"));
    });
});

test('a member of the institution can not be invited again', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    User::factory()->for($institution)->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'Otro', 'email' => 'taken@example.com', 'roles' => ['Docente'],
    ])->assertSessionHasErrors(['email' => 'Esta persona ya es miembro del plantel.']);

    Notification::assertNothingSent();
});

test('an email with a pending invitation can not be invited twice', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    invite($admin, $institution, 'twice@example.com');

    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'Otra vez', 'email' => 'twice@example.com', 'roles' => ['Docente'],
    ])->assertSessionHasErrors('email');
});

test('inviting someone who belongs to another institution gets the same answer as anyone else', function () {
    Notification::fake();

    $institutionA = tenant();
    User::factory()->for($institutionA)->create(['email' => 'shared@example.com']);

    $institutionB = tenant();
    $admin = userWithRole($institutionB, 'Administrador');

    $answers = collect(['shared@example.com', 'nobody@example.com'])->map(fn (string $email) => $this->actingAs($admin)
        ->post(tenantUrl($institutionB, '/users'), ['name' => 'Alguien', 'email' => $email, 'roles' => ['Estudiante']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'))
        ->getSession()->get('success')
    );

    expect($answers[0])->toBe(str_replace('nobody@', 'shared@', $answers[1]));
    expect(InvitationModel::query()->where('email', 'shared@example.com')->sole()->institution_id)->toBe($institutionB->id);
});

test('only roles that exist in the institution can be assigned', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'X', 'email' => 'x@example.com', 'roles' => ['Superusuario'],
    ])->assertSessionHasErrors('roles.0');
});

test('the edit screen shows the user with their role', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');

    $this->actingAs($admin)->get(tenantUrl($institution, "/users/{$teacher->id}/edit"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Edit')
            ->where('user.id', $teacher->id)
            ->where('user.roles', ['Docente'])
            ->where('roles', ['Administrador', 'Director', 'Coordinador', 'Docente', 'Representante', 'Estudiante'])
        );
});

test("an administrator can change a user's role and deactivate them", function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');

    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$teacher->id}"), [
        'roles' => ['Representante'],
        'status' => 'inactive',
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    expect(membershipStatus($teacher))->toBe('suspended')
        ->and($teacher->fresh()->isActive())->toBeFalse()
        ->and($teacher->fresh()->getRoleNames()->all())->toBe(['Representante']);
});

test('a suspended member is reactivated with the roles they had', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');
    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$teacher->id}"), ['roles' => ['Docente'], 'status' => 'inactive']);

    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$teacher->id}"), [
        'roles' => ['Docente'], 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect(membershipStatus($teacher))->toBe('active')
        ->and($teacher->fresh()->getRoleNames()->all())->toBe(['Docente']);
});

test('suspending a member keeps their membership in other institutions', function () {
    $institutionA = tenant();
    $teacher = userWithRole($institutionA, 'Docente');
    $institutionB = tenant();
    $admin = userWithRole($institutionB, 'Administrador');
    joinInstitution($teacher, $institutionB, 'Docente');

    $this->actingAs($admin)->put(tenantUrl($institutionB, "/users/{$teacher->id}"), [
        'roles' => ['Docente'], 'status' => 'inactive',
    ])->assertSessionHasNoErrors();

    expect(DB::table('institution_memberships')->where('user_id', $teacher->id)->value('status'))->toBe('suspended');

    app(TenantActivator::class)->activate(TenantContext::of(InstitutionMapper::toDomain($institutionA)));
    expect(membershipStatus($teacher))->toBe('active');
});

test('administrators can not deactivate themselves or give up managing users', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $response = $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'roles' => ['Docente'],
        'status' => 'inactive',
    ]);

    $response->assertSessionHasErrors(['roles', 'status']);
    expect($admin->fresh()->isActive())->toBeTrue();
    expect($admin->fresh()->hasRole('Administrador'))->toBeTrue();
});

test('the last active administrador can not be suspended', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'roles' => ['Administrador'], 'status' => 'inactive',
    ])->assertSessionHasErrors(['roles' => 'El plantel debe tener al menos un Administrador activo.']);

    expect(membershipStatus($admin))->toBe('active');
});

test('a manager can not change the name or email of a member', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente', ['name' => 'Ana Pérez', 'email' => 'ana@example.com']);

    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$teacher->id}"), [
        'name' => 'Otro Nombre', 'email' => 'otro@example.com', 'roles' => ['Docente'], 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($teacher->fresh()->only('name', 'email'))->toBe(['name' => 'Ana Pérez', 'email' => 'ana@example.com']);
});

test('a user of another institution can not be edited', function () {
    $institutionA = tenant();
    $outsider = User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    $admin = userWithRole($institutionB, 'Administrador');

    $this->actingAs($admin)->get(tenantUrl($institutionB, "/users/{$outsider->id}/edit"))->assertNotFound();
    $this->actingAs($admin)->put(tenantUrl($institutionB, "/users/{$outsider->id}"), [
        'roles' => ['Docente'], 'status' => 'active',
    ])->assertNotFound();
});

test('a pending invitation can be resent', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $invitation = invite($admin, $institution, 'invited@example.com');

    $this->actingAs($admin)->post(tenantUrl($institution, "/invitations/{$invitation->id}/resend"))
        ->assertSessionHas('success');

    Notification::assertSentOnDemandTimes(InvitationMail::class, 2);
    expect($invitation->fresh()->token_hash)->not->toBe($invitation->token_hash);
});

test('an invitation that is no longer pending can not be resent', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $invitation = invite($admin, $institution, 'invited@example.com');
    $invitation->forceFill(['status' => 'accepted'])->save();

    $this->actingAs($admin)->post(tenantUrl($institution, "/invitations/{$invitation->id}/resend"))
        ->assertStatus(409);

    Notification::assertSentOnDemandTimes(InvitationMail::class, 1);
});

test('a pending invitation can be corrected, which replaces its link', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $invitation = invite($admin, $institution, 'tpyo@example.com', name: 'Nombre Mal');

    $this->actingAs($admin)->get(tenantUrl($institution, "/invitations/{$invitation->id}/edit"))
        ->assertInertia(fn ($page) => $page->component('Users/EditInvitation')->where('invitation.email', 'tpyo@example.com'));

    $this->actingAs($admin)->put(tenantUrl($institution, "/invitations/{$invitation->id}"), [
        'name' => 'Nombre Bien', 'email' => 'typo@example.com',
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $corrected = $invitation->fresh();
    expect($corrected->only('name', 'email'))->toBe(['name' => 'Nombre Bien', 'email' => 'typo@example.com'])
        ->and($corrected->token_hash)->not->toBe($invitation->token_hash);

    Notification::assertSentOnDemand(InvitationMail::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'typo@example.com');
});

test('a pending invitation can be cancelled', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $invitation = invite($admin, $institution, 'gone@example.com');

    $this->actingAs($admin)->delete(tenantUrl($institution, "/invitations/{$invitation->id}"))
        ->assertRedirect(route('users.index'));

    expect($invitation->fresh()->status)->toBe('cancelled');
});

test('an invitation of another institution can not be managed', function () {
    Notification::fake();

    $institutionA = tenant();
    $invitation = invite(userWithRole($institutionA, 'Administrador'), $institutionA, 'a@example.com');

    $institutionB = tenant();
    $admin = userWithRole($institutionB, 'Administrador');

    $this->actingAs($admin)->get(tenantUrl($institutionB, "/invitations/{$invitation->id}/edit"))->assertNotFound();
    $this->actingAs($admin)->post(tenantUrl($institutionB, "/invitations/{$invitation->id}/resend"))->assertNotFound();
    $this->actingAs($admin)->delete(tenantUrl($institutionB, "/invitations/{$invitation->id}"))->assertNotFound();
});

test('invitations and membership changes are recorded in the audit log', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');
    invite($admin, $institution, 'audit@example.com');
    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$teacher->id}"), ['roles' => ['Coordinador'], 'status' => 'inactive']);

    $entries = DB::table('audit_entries')->orderBy('id')->get(['event_type', 'actor_user_id', 'changes']);

    expect($entries->pluck('event_type')->all())->toBe([
        'identity.invitation.created',
        'identity.membership.roles_changed',
        'identity.membership.suspended',
    ])
        ->and($entries->pluck('actor_user_id')->unique()->all())->toBe([$admin->id])
        ->and($entries->pluck('changes')->implode(' '))->not->toContain('audit@example.com');
});
