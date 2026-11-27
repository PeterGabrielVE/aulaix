<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\InvitationMail;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * SPEC-009 EST-R08.1 over HTTP: a guardian invited from its student's record
 * gets its account linked once it accepts the invitation.
 */
beforeEach(function () {
    Notification::fake();
    $this->institution = tenant();
    $this->admin = userWithRole($this->institution, 'Administrador');
});

function studentWithGuardianEmail(Institution $institution, string $email): int
{
    test()->actingAs(test()->admin)->post(tenantUrl($institution, '/estudiantes'), [
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => 'V11915432100',
        'guardians' => [[
            'given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id', 'document' => 'V15432100',
            'phone' => '0414', 'email' => $email, 'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true,
        ]],
    ])->assertSessionHasNoErrors();

    return (int) DB::table('guardians')->value('id');
}

it('invites the guardian and links the account that accepts the invitation', function () {
    $guardian = studentWithGuardianEmail($this->institution, 'maria@correo.ve');

    $this->actingAs($this->admin)->post(tenantUrl($this->institution, "/representantes/{$guardian}/invitacion"))->assertSessionHasNoErrors();

    $url = null;
    Notification::assertSentOnDemand(InvitationMail::class, function (InvitationMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    expect(DB::table('guardians')->value('pending_invitation_id'))->not->toBeNull()
        ->and(DB::table('invitations')->where('email', 'maria@correo.ve')->value('roles'))->toContain('Representante');

    leaveTenant();
    $this->post(centralUrl("/invitaciones/{$this->institution->subdomain}"), [
        'token' => basename(parse_url($url, PHP_URL_PATH)), 'email' => 'maria@correo.ve',
        'password' => 'mi-contrasena-nueva', 'password_confirmation' => 'mi-contrasena-nueva',
    ])->assertSessionHasNoErrors();

    useTenant($this->institution);
    expect(DB::table('guardians')->first())
        ->account_id->toBe((int) DB::table('users')->where('email', 'maria@correo.ve')->value('id'))
        ->pending_invitation_id->toBeNull();
});

it('links straight away a guardian who already is a member of the institution', function () {
    $teacher = userWithRole($this->institution, 'Docente', ['email' => 'maria@correo.ve']);
    $guardian = studentWithGuardianEmail($this->institution, 'maria@correo.ve');

    $this->actingAs($this->admin)->post(tenantUrl($this->institution, "/representantes/{$guardian}/invitacion"))->assertSessionHasNoErrors();

    Notification::assertNothingSent();
    expect(DB::table('guardians')->value('account_id'))->toBe($teacher->id);
});

it('makes a member linked as guardian a Representante too, and records it (SPEC-013 EST-R08.7)', function () {
    $teacher = userWithRole($this->institution, 'Docente', ['email' => 'maria@correo.ve']);
    $guardian = studentWithGuardianEmail($this->institution, 'maria@correo.ve');

    $this->actingAs($this->admin)->post(tenantUrl($this->institution, "/representantes/{$guardian}/invitacion"))->assertSessionHasNoErrors();

    expect($teacher->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Docente', 'Representante'])
        ->and(DB::table('audit_entries')->where('event_type', 'identity.membership.roles_changed')->value('actor_user_id'))->toBe($this->admin->id)
        ->and(DB::table('audit_entries')->where('event_type', 'students.guardian.account_linked')->exists())->toBeTrue();
});

it('needs the guardian email', function () {
    $guardian = studentWithGuardianEmail($this->institution, '');

    $this->actingAs($this->admin)->post(tenantUrl($this->institution, "/representantes/{$guardian}/invitacion"))->assertSessionHasErrors('email');
});
