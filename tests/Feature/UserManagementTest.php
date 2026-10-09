<?php

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Notification;

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

    $this->actingAs($director)->post(tenantUrl($institution, '/users'), [
        'name' => 'Luis Rojas', 'email' => 'luis@example.com', 'roles' => ['Coordinador'],
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'luis@example.com')->sole()->hasRole('Coordinador'))->toBeTrue();
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
        'name' => $director->name, 'email' => $director->email,
        'roles' => ['Director', 'Administrador'], 'status' => 'active',
    ])->assertSessionHasErrors('roles');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$teacher->id}"), [
        'name' => $teacher->name, 'email' => $teacher->email,
        'roles' => ['Administrador'], 'status' => 'active',
    ])->assertSessionHasErrors('roles');

    expect(User::where('email', 'nuevo@example.com')->exists())->toBeFalse();
    expect($director->fresh()->hasRole('Administrador'))->toBeFalse();
    expect($teacher->fresh()->hasRole('Administrador'))->toBeFalse();
});

test('a director can not take the administrador role away', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'name' => $admin->name, 'email' => $admin->email,
        'roles' => ['Docente'], 'status' => 'active',
    ])->assertSessionHasErrors('roles');

    expect($admin->fresh()->hasRole('Administrador'))->toBeTrue();
});

test('a director can edit an administrador without touching that role', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'name' => 'Nombre corregido', 'email' => $admin->email,
        'roles' => ['Administrador', 'Docente'], 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($admin->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Administrador', 'Docente']);
});

test('a director can not give up managing users', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');

    $this->actingAs($director)->put(tenantUrl($institution, "/users/{$director->id}"), [
        'name' => $director->name, 'email' => $director->email,
        'roles' => ['Coordinador'], 'status' => 'active',
    ])->assertSessionHasErrors(['roles' => 'No puedes quitarte a ti mismo la gestión de usuarios.']);

    expect($director->fresh()->hasRole('Director'))->toBeTrue();
});

test('an administrador can not swap their own role for director', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    // Director also manages users, but can't grant Administrador back.
    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'name' => $admin->name, 'email' => $admin->email,
        'roles' => ['Director'], 'status' => 'active',
    ])->assertSessionHasErrors(['roles' => 'No puedes quitarte a ti mismo el rol de Administrador.']);

    expect($admin->fresh()->getRoleNames()->all())->toBe(['Administrador']);
});

test('an administrador can add roles to themselves', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'name' => $admin->name, 'email' => $admin->email,
        'roles' => ['Administrador', 'Representante'], 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($admin->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Administrador', 'Representante']);
});

test('a user can be given several roles in the same institution', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'Ana Díaz', 'email' => 'ana@example.com', 'roles' => ['Representante', 'Docente'],
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'ana@example.com')->sole();

    $this->actingAs($admin)->get(tenantUrl($institution, "/users/{$user->id}/edit"))
        ->assertInertia(fn ($page) => $page->where('user.roles', ['Docente', 'Representante']));
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

test('an administrator creates a user with a role and an invitation is sent', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $response = $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'María Pérez',
        'email' => 'maria@example.com',
        'roles' => ['Docente'],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $user = User::where('email', 'maria@example.com')->sole();
    expect($user->institution_id)->toBe($institution->id);
    expect($user->isActive())->toBeTrue();
    expect($user->hasRole('Docente'))->toBeTrue();
    expect($user->hasAcceptedInvitation())->toBeFalse();

    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user, $institution) {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, tenantUrl($institution, '/invitation/'.$notification->token));
    });
});

test('the email must be unique within the institution', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    User::factory()->for($institution)->create(['email' => 'taken@example.com']);

    $response = $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'Otro', 'email' => 'taken@example.com', 'roles' => ['Docente'],
    ]);

    $response->assertSessionHasErrors('email');
});

test('the same email can be used in a different institution', function () {
    Notification::fake();

    $institutionA = tenant();
    User::factory()->for($institutionA)->create(['email' => 'shared@example.com']);

    $institutionB = tenant();
    $admin = userWithRole($institutionB, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institutionB, '/users'), [
        'name' => 'Compartido', 'email' => 'shared@example.com', 'roles' => ['Estudiante'],
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'shared@example.com')->sole()->institution_id)->toBe($institutionB->id);
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
        'name' => $teacher->name,
        'email' => $teacher->email,
        'roles' => ['Representante'],
        'status' => UserStatus::Inactive->value,
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $teacher->refresh();
    expect($teacher->isActive())->toBeFalse();
    expect($teacher->getRoleNames()->all())->toBe(['Representante']);
});

test('administrators can not deactivate themselves or give up managing users', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $response = $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'name' => $admin->name,
        'email' => $admin->email,
        'roles' => ['Docente'],
        'status' => UserStatus::Inactive->value,
    ]);

    $response->assertSessionHasErrors(['roles', 'status']);
    expect($admin->fresh()->isActive())->toBeTrue();
    expect($admin->fresh()->hasRole('Administrador'))->toBeTrue();
});

test('a user of another institution can not be edited', function () {
    $institutionA = tenant();
    $outsider = User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    $admin = userWithRole($institutionB, 'Administrador');

    $this->actingAs($admin)->get(tenantUrl($institutionB, "/users/{$outsider->id}/edit"))->assertNotFound();
    $this->actingAs($admin)->put(tenantUrl($institutionB, "/users/{$outsider->id}"), [
        'name' => 'Hackeado', 'email' => 'h@example.com', 'roles' => ['Docente'], 'status' => 'active',
    ])->assertNotFound();
});

test('a pending invitation can be resent', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $invited = userWithRole($institution, 'Docente', ['email_verified_at' => null]);

    $this->actingAs($admin)->post(tenantUrl($institution, "/users/{$invited->id}/invitation"))
        ->assertSessionHas('success');

    Notification::assertSentTo($invited, UserInvitation::class);
});

test('an invitation can not be resent to a user who already activated their account', function () {
    Notification::fake();

    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');

    $this->actingAs($admin)->post(tenantUrl($institution, "/users/{$teacher->id}/invitation"))
        ->assertStatus(409);

    Notification::assertNothingSent();
});
