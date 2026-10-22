<?php

declare(strict_types=1);

namespace AulaX\Students\Application\GuardianAccount;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\GuardianNotFound;
use AulaX\Students\Application\Port\GuardianInvitations;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Guardian\GuardianRepository;

/**
 * A manager gives a guardian access to AulaX (EST-R08.1): an invitation as
 * Representante, whose account is linked once accepted; or, when the email
 * already belongs to a member of the institution, that account straight
 * away (IdentityAccess does not invite its own members).
 */
final readonly class InviteGuardianHandler
{
    public function __construct(
        private GuardianRepository $guardians,
        private GuardianInvitations $invitations,
        private StudentPermissions $permissions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws BusinessRuleViolations
     * @throws GuardianNotFound
     */
    public function __invoke(int $guardianId, int $actorId): void
    {
        StudentAccess::ensureCanManage($this->permissions);
        $id = $guardianId > 0 ? GuardianId::fromInt($guardianId) : throw GuardianNotFound::create();
        $guardian = $this->guardians->byId($id) ?? throw GuardianNotFound::create();
        $email = $guardian->data()->email;

        if ($guardian->hasAccess()) {
            throw BusinessRuleViolations::of(BusinessRuleViolation::onField('guardian', 'Este representante ya tiene acceso a AulaX.'));
        }

        if ($email === null) {
            throw BusinessRuleViolations::of(BusinessRuleViolation::onField('email', 'El representante necesita un correo para invitarlo.'));
        }

        $member = $this->invitations->memberWithEmail($email);

        if ($member !== null) {
            $this->transactions->run(function () use ($guardian, $member) {
                $guardian->linkAccount($member);
                $this->guardians->save($guardian);
                $this->audit->record(StudentAudit::guardianLinked($guardian));
            });

            return;
        }

        // IdentityAccess runs and records the invitation in its own transaction.
        $invitationId = $this->invitations->invite($actorId, $email, $guardian->data()->fullName());

        $this->transactions->run(function () use ($guardian, $invitationId) {
            $guardian->awaitInvitation($invitationId);
            $this->guardians->save($guardian);
            $this->audit->record(StudentAudit::guardianInvited($guardian, $invitationId));
        });
    }
}
