<?php

use AulaX\IdentityAccess\Application\Events\MemberJoined;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\GuardianAccount\InviteGuardianHandler;
use AulaX\Students\Application\GuardianAccount\LinkGuardianAccountHandler;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Tenancy\Application\InstitutionContexts;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\Subdomain;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Students\FakeGuardianInvitations;
use Tests\Fixtures\Students\InMemoryGuardianRepository;
use Tests\Fixtures\Students\SwitchableStudentPermissions;
use Tests\Fixtures\Tenancy\InMemoryInstitutionRepository;
use Tests\Fixtures\Tenancy\SwitchingTenantActivator;

/**
 * SPEC-009 EST-R08.1: a guardian gets access through an invitation, its
 * account linked when the invitation is accepted, or straight away when it
 * is already a member.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->guardians = new InMemoryGuardianRepository;
    $this->invitations = new FakeGuardianInvitations;
    $this->permissions = new SwitchableStudentPermissions;
    $this->tenant = new InMemoryCurrentTenant;
    $this->activator = new SwitchingTenantActivator($this->tenant);
    $this->institutions = new InMemoryInstitutionRepository(Institution::reconstitute(
        InstitutionId::fromInt(4), 'Colegio Norte', Subdomain::fromString('norte'), InstitutionStatus::Active, InstitutionProfile::empty(),
    ));
});

function savedGuardianWithEmail(?string $email = 'maria@correo.ve'): GuardianId
{
    return test()->guardians->save(Guardian::register(GuardianData::fromInput('María', 'Mora', 'national_id', 'V15432100', '0414', $email)));
}

function inviteGuardian(GuardianId $id): void
{
    $test = test();
    (new InviteGuardianHandler($test->guardians, $test->invitations, $test->permissions, $test->audit, $test->transactions))($id->value, 7);
}

function memberJoined(int $invitationId, int $accountId = 90): void
{
    $test = test();
    (new LinkGuardianAccountHandler($test->guardians, $test->activator, $test->tenant, new InstitutionContexts($test->institutions), $test->audit, $test->transactions))(
        new MemberJoined(4, $accountId, $invitationId, new DateTimeImmutable('2026-10-22')),
    );
}

it('invites the guardian as Representante, keeping the invitation to link it later', function () {
    $id = savedGuardianWithEmail();

    inviteGuardian($id);

    expect($this->invitations->sent)->toBe([['actor' => 7, 'email' => 'maria@correo.ve', 'name' => 'María Mora']])
        ->and($this->guardians->byId($id)->pendingInvitationId())->toBe(501)
        ->and($this->guardians->byId($id)->accountId())->toBeNull()
        ->and($this->audit->types())->toBe(['students.guardian.invited']);
});

it('links the account when the invitation becomes a member, in that institution', function () {
    $id = savedGuardianWithEmail();
    inviteGuardian($id);

    memberJoined(501);

    expect($this->guardians->byId($id)->accountId())->toBe(90)
        ->and($this->guardians->byId($id)->pendingInvitationId())->toBeNull()
        ->and($this->activator->activated)->toBe([4])
        ->and($this->tenant->context())->toBeNull()
        ->and($this->audit->types())->toBe(['students.guardian.invited', 'students.guardian.account_linked']);
});

it('ignores members joined through invitations of no guardian', function () {
    savedGuardianWithEmail();

    memberJoined(777);

    expect($this->audit->types())->toBe([]);
});

it('goes back to the institution that was open', function () {
    $this->tenant->set(new TenantContext(InstitutionId::fromInt(8), 'Otro', 'otro'));

    memberJoined(777);

    expect($this->tenant->institutionId()->value)->toBe(8);
});

it('links straight away a guardian who already is a member', function () {
    $id = savedGuardianWithEmail();
    $this->invitations->members['maria@correo.ve'] = 33;

    inviteGuardian($id);

    expect($this->invitations->sent)->toBe([])
        ->and($this->guardians->byId($id)->accountId())->toBe(33)
        ->and($this->audit->types())->toBe(['students.guardian.account_linked']);
});

it('makes the member it links a Representante, before linking (SPEC-013 EST-R08.7)', function () {
    $id = savedGuardianWithEmail();
    $this->invitations->members['maria@correo.ve'] = 33;

    inviteGuardian($id);

    expect($this->invitations->granted)->toBe([['actor' => 7, 'member' => 33]]);
});

it('needs an email, and a guardian without access yet', function () {
    $withoutEmail = savedGuardianWithEmail(null);

    expect(fn () => inviteGuardian($withoutEmail))->toThrow(BusinessRuleViolations::class, 'El representante necesita un correo para invitarlo.');

    $member = test()->guardians->save(Guardian::register(GuardianData::fromInput('Luis', 'Pérez', 'national_id', 'V20111222', '0424', 'luis@correo.ve')));
    $this->invitations->members['luis@correo.ve'] = 34;
    inviteGuardian($member);

    expect(fn () => inviteGuardian($member))->toThrow(BusinessRuleViolations::class, 'Este representante ya tiene acceso a AulaX.');
});

it('refuses whoever may not manage students', function () {
    $this->permissions->manage = false;

    expect(fn () => inviteGuardian(savedGuardianWithEmail()))->toThrow(AuthorizationDenied::class);
});
