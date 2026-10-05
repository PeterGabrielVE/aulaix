<?php

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

test('users without the gestionar-usuarios permission can not manage users', function () {
    $institution = tenant();
    $teacher = userWithRole($institution, 'Docente');

    $this->actingAs($teacher)->get(tenantUrl($institution, '/users'))->assertForbidden();
    $this->actingAs($teacher)->get(tenantUrl($institution, '/users/create'))->assertForbidden();
    $this->actingAs($teacher)->post(tenantUrl($institution, '/users'), [
        'name' => 'Nuevo', 'email' => 'nuevo@example.com', 'role' => 'Administrador',
    ])->assertForbidden();
});

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
        'role' => 'Docente',
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
        'name' => 'Otro', 'email' => 'taken@example.com', 'role' => 'Docente',
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
        'name' => 'Compartido', 'email' => 'shared@example.com', 'role' => 'Estudiante',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'shared@example.com')->sole()->institution_id)->toBe($institutionB->id);
});

test('only roles that exist in the institution can be assigned', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), [
        'name' => 'X', 'email' => 'x@example.com', 'role' => 'Superusuario',
    ])->assertSessionHasErrors('role');
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
            ->where('user.role', 'Docente')
            ->where('roles', ['Administrador', 'Docente', 'Representante', 'Estudiante'])
        );
});

test("an administrator can change a user's role and deactivate them", function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');

    $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$teacher->id}"), [
        'name' => $teacher->name,
        'email' => $teacher->email,
        'role' => 'Representante',
        'status' => User::STATUS_INACTIVE,
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $teacher->refresh();
    expect($teacher->isActive())->toBeFalse();
    expect($teacher->getRoleNames()->all())->toBe(['Representante']);
});

test('administrators can not deactivate or demote themselves', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $response = $this->actingAs($admin)->put(tenantUrl($institution, "/users/{$admin->id}"), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'Docente',
        'status' => User::STATUS_INACTIVE,
    ]);

    $response->assertSessionHasErrors(['role', 'status']);
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
        'name' => 'Hackeado', 'email' => 'h@example.com', 'role' => 'Docente', 'status' => 'active',
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
