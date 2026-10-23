<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-004 IDG-R03.7, R03.9, IDG-NF03: from the central domain into an
 * institution without typing the password again.
 */
function twoInstitutionTeacher(): array
{
    $norte = tenant();
    $teacher = userWithRole($norte, 'Docente', ['email' => 'ana@example.com']);
    $sur = tenant();
    joinInstitution($teacher, $sur, 'Docente');

    leaveTenant();
    test()->post(centralUrl('/login'), ['email' => 'ana@example.com', 'password' => 'password']);

    return [$teacher, $norte, $sur];
}

/**
 * Sessions are per domain (their own cookie): what the subdomain sees before
 * the handoff is an empty session, not the central one.
 */
function onSubdomainSession(): void
{
    test()->flushSession();
    auth()->forgetGuards();
}

/**
 * Open the institution from the central selector; returns the handoff URL.
 */
function chooseInstitution(Institution $institution): string
{
    $location = test()->post(centralUrl("/planteles/{$institution->subdomain}"))->headers->get('Location');

    onSubdomainSession();

    return $location;
}

test('choosing an institution opens it without asking for the password again', function () {
    [$teacher, , $sur] = twoInstitutionTeacher();

    $handoff = chooseInstitution($sur);
    expect($handoff)->toStartWith(tenantUrl($sur, '/auth/handoff?token='));

    $this->get($handoff)->assertRedirect(tenantUrl($sur, '/dashboard'));
    $this->assertAuthenticatedAs($teacher);
    $this->get(tenantUrl($sur, '/dashboard'))->assertRedirect(tenantUrl($sur, '/teacher'));
});

test('a handoff link works only once', function () {
    [, , $sur] = twoInstitutionTeacher();
    $handoff = chooseInstitution($sur);
    $this->get($handoff);
    onSubdomainSession();

    $this->get($handoff)->assertRedirect(centralUrl('/login'));
    $this->assertGuest();
});

test('a handoff link expires after a minute', function () {
    [, , $sur] = twoInstitutionTeacher();
    $handoff = chooseInstitution($sur);

    $this->travel(61)->seconds();

    $this->get($handoff)->assertRedirect(centralUrl('/login'));
    $this->assertGuest();
});

test('a handoff link does not open another institution', function () {
    [, $norte, $sur] = twoInstitutionTeacher();
    $token = parse_url(chooseInstitution($sur), PHP_URL_QUERY);

    $this->get(tenantUrl($norte, "/auth/handoff?{$token}"))->assertRedirect(centralUrl('/login'));
    $this->assertGuest();
});

test('a membership suspended after choosing is not let in', function () {
    [$teacher, , $sur] = twoInstitutionTeacher();
    $handoff = chooseInstitution($sur);
    useTenant($sur);
    DB::table('institution_memberships')->where('user_id', $teacher->id)->update(['status' => 'suspended', 'suspension_reason' => 'institution']);

    $this->get($handoff)->assertRedirect(centralUrl('/login'));
    $this->assertGuest();
});

test('an institution without membership answers like one that does not exist', function () {
    twoInstitutionTeacher();
    $foreign = tenant();
    leaveTenant();
    $this->actingAs(User::query()->where('email', 'ana@example.com')->sole());

    $this->post(centralUrl("/planteles/{$foreign->subdomain}"))->assertNotFound();
    $this->post(centralUrl('/planteles/no-existe'))->assertNotFound();
});

test('the institution menu offers to switch institution', function () {
    // SPEC-013 NAV-R02.5 narrows IDG-R03.7: offered to who has another institution.
    [, , $sur] = twoInstitutionTeacher();
    $this->get(chooseInstitution($sur));

    $this->get(tenantUrl($sur, '/profile'))
        ->assertInertia(fn ($page) => $page->where('switchInstitutionUrl', centralUrl('/planteles')));
});

test('a member of one institution is not offered to switch (SPEC-013 NAV-R02.6)', function () {
    $institution = tenant();
    userWithRole($institution, 'Docente', ['email' => 'luis@example.com']);
    leaveTenant();
    $handoff = $this->post(centralUrl('/login'), ['email' => 'luis@example.com', 'password' => 'password'])->headers->get('Location');
    onSubdomainSession();
    $this->get($handoff)->assertRedirect(tenantUrl($institution, '/dashboard'));

    $this->get(tenantUrl($institution, '/profile'))
        ->assertInertia(fn ($page) => $page->where('switchInstitutionUrl', null));
});
