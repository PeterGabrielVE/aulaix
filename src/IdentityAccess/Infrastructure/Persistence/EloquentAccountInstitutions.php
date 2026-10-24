<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Application\Auth\AccountInstitutions;
use AulaX\IdentityAccess\Application\Auth\InstitutionChoice;
use AulaX\IdentityAccess\Application\Auth\PendingInvitationView;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Invitation\InvitationStatus;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Memberships and invitations across institutions, readable with no
 * institution active (ADR 0006 C). Inactive institutions are left out: they
 * can not be opened (TEN-R03).
 */
final class EloquentAccountInstitutions implements AccountInstitutions
{
    public function activeFor(AccountId $account): array
    {
        return DB::table('institution_memberships as membership')
            ->join('institutions', 'institutions.id', '=', 'membership.institution_id')
            ->where('membership.user_id', $account->value)
            ->where('membership.status', MembershipStatus::Active->value)
            ->where('institutions.status', 'active')
            ->orderBy('institutions.name')
            ->get(['institutions.id', 'institutions.name', 'institutions.subdomain'])
            ->map(fn (object $row) => new InstitutionChoice((int) $row->id, $row->name, $row->subdomain))
            ->values()
            ->all();
    }

    public function pendingInvitationsFor(EmailAddress $email): array
    {
        return InvitationModel::query()
            ->join('institutions', 'institutions.id', '=', 'invitations.institution_id')
            ->where('invitations.email', $email->value)
            ->where('invitations.status', InvitationStatus::Pending->value)
            ->where('invitations.expires_at', '>=', Date::now())
            ->where('institutions.status', 'active')
            ->latest('invitations.id')
            ->get(['invitations.id', 'invitations.roles', 'invitations.expires_at', 'institutions.name as institution', 'institutions.subdomain'])
            ->map(fn (InvitationModel $row) => new PendingInvitationView(
                (int) $row->id,
                $row->getAttribute('institution'),
                $row->getAttribute('subdomain'),
                RoleSet::of(...$row->roles)->inPriorityOrder(),
                $row->expires_at,
            ))
            ->values()
            ->all();
    }
}
