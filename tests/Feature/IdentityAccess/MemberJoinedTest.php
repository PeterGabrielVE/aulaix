<?php

use AulaX\IdentityAccess\Application\Events\MemberJoined;
use AulaX\IdentityAccess\Application\Members\InviteMemberCommand;
use AulaX\IdentityAccess\Application\Members\InviteMemberHandler;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\IdentityAccess\Infrastructure\Notifications\InvitationMail;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * SPEC-009 EST-R08.1 (prerequisites in IdentityAccess): other modules learn
 * which invitation turned into which member, and can find a member by email.
 */

/**
 * The token of an invitation sent from the users screen.
 */
function joinedInvitationToken(Institution $institution, string $email): string
{
    Notification::fake();
    test()->actingAs(userWithRole($institution, 'Administrador'))
        ->post(tenantUrl($institution, '/users'), ['name' => 'Ana Pérez', 'email' => $email, 'roles' => ['Representante']])
        ->assertSessionHasNoErrors();

    $url = null;
    Notification::assertSentOnDemand(InvitationMail::class, function (InvitationMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    leaveTenant();

    return basename(parse_url($url, PHP_URL_PATH));
}

it('gives back the id of the invitation it issues', function () {
    Notification::fake();
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $id = app(InviteMemberHandler::class)(new InviteMemberCommand($admin->id, 'ana@example.com', 'Ana Pérez', ['Representante']));

    expect(DB::table('invitations')->where('id', $id)->value('email'))->toBe('ana@example.com');
});

it('tells that an invitation was accepted by a new account', function () {
    Event::fake([MemberJoined::class]);
    $institution = tenant();
    $token = joinedInvitationToken($institution, 'ana@example.com');
    $invitationId = (int) DB::table('invitations')->max('id');

    $this->post(centralUrl("/invitaciones/{$institution->subdomain}"), [
        'token' => $token, 'email' => 'ana@example.com', 'password' => 'mi-contrasena-nueva', 'password_confirmation' => 'mi-contrasena-nueva',
    ])->assertSessionHasNoErrors();

    Event::assertDispatched(MemberJoined::class, fn (MemberJoined $event) => $event->institutionId === $institution->id
        && $event->invitationId === $invitationId
        && $event->accountId === (int) DB::table('users')->where('email', 'ana@example.com')->value('id'));
});

it('tells that an invitation was accepted by an existing account', function () {
    Event::fake([MemberJoined::class]);
    $elsewhere = tenant();
    $ana = userWithRole($elsewhere, 'Docente', ['email' => 'ana@example.com', 'password' => 'la-de-siempre']);
    $institution = tenant();
    joinedInvitationToken($institution, 'ana@example.com');
    $this->post(centralUrl('/login'), ['email' => 'ana@example.com', 'password' => 'la-de-siempre']);

    $this->post(centralUrl("/invitaciones/{$institution->subdomain}/unirme"), ['email' => 'ana@example.com']);

    Event::assertDispatched(MemberJoined::class, fn (MemberJoined $event) => $event->institutionId === $institution->id && $event->accountId === $ana->id);
});

it('finds an active member of the open institution by email, not someone else', function () {
    $institution = tenant();
    $member = userWithRole($institution, 'Docente', ['email' => 'luis@example.com']);
    $elsewhere = tenant();
    userWithRole($elsewhere, 'Docente', ['email' => 'otra@example.com']);
    useTenant($institution);

    expect(app(MemberDirectory::class)->activeMemberByEmail(' Luis@Example.com '))->toBe($member->id)
        ->and(app(MemberDirectory::class)->activeMemberByEmail('otra@example.com'))->toBeNull()
        ->and(app(MemberDirectory::class)->activeMemberByEmail('no es correo'))->toBeNull();
});
