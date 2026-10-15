<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Invitation\Invitation;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\IdentityAccess\Domain\Invitation\InvitationStatus;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Conflict;
use AulaX\Shared\Domain\NotFound;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use LogicException;

/**
 * Checks shared by the manager's use cases. Every read goes through the
 * active institution (Row Level Security), so none of them can tell whether
 * an email has an account elsewhere (IDG-R04.2, IDG-NF02).
 */
final readonly class MemberRules
{
    public function __construct(
        private CurrentTenant $tenant,
        private RoleCatalog $roles,
        private AccountRepository $accounts,
        private MembershipRepository $memberships,
        private InvitationRepository $invitations,
    ) {}

    public function institution(): TenantContext
    {
        return $this->tenant->context()
            ?? throw new LogicException('Managing members needs an active institution.');
    }

    /**
     * A pending invitation of the active institution, the only kind a
     * manager may still change.
     *
     * @throws NotFound When it does not exist in this institution.
     * @throws Conflict When it was already accepted or cancelled.
     */
    public function pendingInvitation(int $id): Invitation
    {
        $invitation = $this->invitations->byId($id) ?? throw new NotFound('La invitación no existe.');

        if ($invitation->status !== InvitationStatus::Pending) {
            throw new Conflict('Esta invitación ya no está pendiente.');
        }

        return $invitation;
    }

    public function unknownRoles(RoleSet $requested): ?BusinessRuleViolation
    {
        $available = $this->roles->available();

        foreach ($requested->names() as $name) {
            if (! $available->has($name)) {
                return BusinessRuleViolation::onField('roles', 'Alguno de los roles no existe en este plantel.');
            }
        }

        return null;
    }

    /**
     * Whether the email can receive a new invitation (or a corrected one):
     * not a member yet (IDG-R04.3) and without another invitation still
     * usable. A pending one that already expired is cancelled to make room.
     */
    public function claimEmail(EmailAddress $email, DateTimeImmutable $now, ?int $exceptInvitationId = null): ?BusinessRuleViolation
    {
        // With an institution active, only the accounts with a membership in
        // it are visible: this never finds an account of another institution.
        foreach ($this->accounts->byEmail($email) as $account) {
            $membership = $this->memberships->ofAccount($account->id);

            if ($membership !== null && $membership->status !== MembershipStatus::Ended) {
                return BusinessRuleViolation::onField('email', 'Esta persona ya es miembro del plantel.');
            }
        }

        $pending = $this->invitations->pendingFor($email);

        if ($pending === null || $pending->id === $exceptInvitationId) {
            return null;
        }

        if ($pending->isUsable($now)) {
            return BusinessRuleViolation::onField('email', 'Ya hay una invitación pendiente para este correo.');
        }

        $this->invitations->save($pending->cancel());

        return null;
    }
}
