<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-004 IDG-R03.1, R03.3 – R03.6, R03.10, R09.3: one login, on the central
 * domain, for every institution a person belongs to.
 */
function centralLogin(string $email, string $password = 'password', string $query = ''): TestResponse
{
    leaveTenant();

    return test()->post(centralUrl('/login'.$query), ['email' => $email, 'password' => $password]);
}

test('the login screen is on the central domain', function () {
    $this->get(centralUrl('/login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('wrong credentials get the same generic answer whether the email has an account or not', function () {
    $institution = tenant();
    userWithRole($institution, 'Docente', ['email' => 'ana@example.com']);

    centralLogin('ana@example.com', 'wrong')->assertSessionHasErrors(['email' => __('auth.failed')]);
    centralLogin('nadie@example.com', 'wrong')->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

test('a person with one active membership goes straight into that institution', function () {
    $institution = tenant();
    userWithRole($institution, 'Docente', ['email' => 'ana@example.com']);

    $response = centralLogin('ana@example.com');

    $this->assertAuthenticated();
    expect($response->headers->get('Location'))->toStartWith(tenantUrl($institution, '/auth/handoff?token='));
});

test('a person with several active memberships chooses among them, and only them', function () {
    $norte = tenant(['name' => 'Colegio Norte']);
    $teacher = userWithRole($norte, 'Docente', ['email' => 'ana@example.com']);
    $sur = tenant(['name' => 'Colegio Sur']);
    joinInstitution($teacher, $sur, 'Docente');
    $este = tenant(['name' => 'Colegio Este']);
    joinInstitution($teacher, $este, 'Docente');
    DB::table('institution_memberships')->where('user_id', $teacher->id)->update(['status' => 'suspended', 'suspension_reason' => 'institution']);

    centralLogin('ana@example.com')->assertRedirect(centralUrl('/planteles'));

    $this->get(centralUrl('/planteles'))
        ->assertInertia(fn ($page) => $page
            ->component('Central/Institutions')
            ->where('institutions', [
                ['name' => 'Colegio Norte', 'subdomain' => $norte->subdomain],
                ['name' => 'Colegio Sur', 'subdomain' => $sur->subdomain],
            ])
        );
});

test('a person without active memberships is welcomed with their pending invitations', function () {
    Notification::fake();
    $old = tenant();
    User::factory()->for($old)->inactive()->create(['email' => 'ana@example.com']);
    $inviting = tenant(['name' => 'Colegio Nuevo']);
    $this->actingAs(userWithRole($inviting, 'Administrador'))
        ->post(tenantUrl($inviting, '/users'), ['name' => 'Ana', 'email' => 'ana@example.com', 'roles' => ['Docente']]);

    centralLogin('ana@example.com')->assertRedirect(centralUrl('/planteles'));

    $this->get(centralUrl('/planteles'))
        ->assertInertia(fn ($page) => $page
            ->where('institutions', [])
            ->has('invitations', 1)
            ->where('invitations.0.institution', 'Colegio Nuevo')
        );
});

test('each historical password of an email awaiting consolidation opens only its own institution', function () {
    $norte = tenant();
    $ana = User::factory()->for($norte)->create(['email' => 'ana@example.com', 'password' => 'clave-norte', 'consolidation_state' => 'pending']);
    $sur = tenant();
    User::factory()->for($sur)->create(['email' => 'ana@example.com', 'password' => 'clave-sur', 'consolidation_state' => 'pending']);

    $response = centralLogin('ana@example.com', 'clave-norte');

    $this->assertAuthenticatedAs($ana);
    expect($response->headers->get('Location'))->toStartWith(tenantUrl($norte, '/auth/handoff?token='));
});

test('an account awaiting consolidation is offered to consolidate', function () {
    $norte = tenant();
    User::factory()->for($norte)->create(['email' => 'ana@example.com', 'consolidation_state' => 'pending']);
    $sur = tenant();
    User::factory()->for($sur)->create(['email' => 'ana@example.com', 'consolidation_state' => 'pending']);
    centralLogin('ana@example.com');

    $this->get(centralUrl('/planteles'))->assertInertia(fn ($page) => $page->where('canConsolidate', true));
});

test('five failed logins lock the email out for a minute, even with the right password', function () {
    $institution = tenant();
    userWithRole($institution, 'Docente', ['email' => 'ana@example.com']);

    foreach (range(1, 5) as $attempt) {
        centralLogin('ana@example.com', 'wrong');
    }

    centralLogin('ana@example.com')->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->travel(61)->seconds();
    centralLogin('ana@example.com');
    $this->assertAuthenticated();
});

test('a closed account can not log in', function () {
    $institution = tenant();
    $user = userWithRole($institution, 'Docente', ['email' => 'ana@example.com']);
    DB::table('users')->where('id', $user->id)->update(['account_state' => 'closed']);

    centralLogin('ana@example.com')->assertSessionHasErrors('email');
    $this->assertGuest();
});
