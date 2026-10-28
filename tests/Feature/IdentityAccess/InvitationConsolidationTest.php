<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\InvitationMail;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\IdentityAccess\Infrastructure\Persistence\InvitationModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-015 RLT-R06, NF05: an account whose email still has other
 * historical accounts joins no institution until the email is
 * consolidated; the invitation waits, untouched.
 */
beforeEach(function () {
    Notification::fake();
    $this->older = userWithRole(tenant(['name' => 'Colegio Norte']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana Pérez', 'consolidation_state' => 'pending']);
    $this->newer = userWithRole(tenant(['name' => 'Colegio Sur']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana Pérez', 'consolidation_state' => 'pending']);
    $this->inviting = tenant(['name' => 'Liceo Oeste']);
    $this->actingAs(userWithRole($this->inviting, 'Administrador'))
        ->post(tenantUrl($this->inviting, '/users'), ['name' => 'Ana Pérez', 'email' => 'ana@example.com', 'roles' => ['Docente']])
        ->assertSessionHasNoErrors();
    Notification::assertSentOnDemand(InvitationMail::class);
    leaveTenant();
    $this->actingAs($this->older);
});

function joinInvitingInstitution(): TestResponse
{
    return test()->from(centralUrl('/planteles'))->post(centralUrl('/invitaciones/'.test()->inviting->subdomain.'/unirme'), ['email' => 'ana@example.com']);
}

function invitationIsUntouched(): void
{
    useTenant(test()->inviting);

    expect(InvitationModel::query()->sole()->status)->toBe('pending')
        ->and(DB::table('institution_memberships')->where('user_id', test()->older->id)->exists())->toBeFalse();

    leaveTenant();
}

test('pending consolidation: offers to consolidate first, and joins nothing (RLT-R06.1)', function () {
    joinInvitingInstitution()->assertSessionHasErrors(['email' => 'Antes de aceptar, unifica tus cuentas con este correo.']);

    invitationIsUntouched();
    $this->actingAs($this->older)->get(centralUrl('/invitaciones/'.$this->inviting->subdomain.'/token?email=ana@example.com'))
        ->assertInertia(fn ($page) => $page->where('consolidation', 'pending'));
});

test('under review: says support is reviewing the accounts (RLT-R06.2)', function () {
    DB::table('users')->where('email', 'ana@example.com')->update(['consolidation_state' => 'review']);

    joinInvitingInstitution()->assertSessionHasErrors(['email' => 'Soporte está revisando las cuentas con este correo. Podrás aceptar la invitación cuando termine.']);

    invitationIsUntouched();
});

test('kept separate: asks for an invitation to another email (RLT-R06.5)', function () {
    DB::table('users')->where('email', 'ana@example.com')->update(['consolidation_state' => 'separated']);

    joinInvitingInstitution()->assertSessionHasErrors(['email' => 'Hay varias cuentas con este correo. Pide al plantel que te invite con otro correo.']);

    invitationIsUntouched();
});

test('the invitation keeps waiting, and is accepted once the email is consolidated (RLT-R06.3, R06.4)', function () {
    joinInvitingInstitution()->assertSessionHasErrors('email');

    // What consolidating leaves: one regular account with that email.
    DB::table('users')->where('id', $this->older->id)->update(['consolidation_state' => null]);
    User::query()->whereKey($this->newer->id)->delete();

    expect(joinInvitingInstitution()->headers->get('Location'))->toStartWith(tenantUrl($this->inviting, '/auth/handoff?token='));

    useTenant($this->inviting);
    expect(InvitationModel::query()->sole()->status)->toBe('accepted');
});
