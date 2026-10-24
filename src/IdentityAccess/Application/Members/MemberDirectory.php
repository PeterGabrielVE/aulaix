<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

/**
 * Read side of the manager's screens: the active institution's members and
 * pending invitations. Never anything about a person's other institutions
 * (IDG-R05.6).
 */
interface MemberDirectory
{
    /**
     * Members (active or suspended) by name, optionally filtered by name or email.
     */
    public function members(?string $search, int $page, int $perPage): MemberPage;

    public function member(int $accountId): ?MemberView;

    /**
     * The account id of the member of the open institution (active or
     * suspended, not ended) with this email, if any. For other modules
     * linking a person they know by email (SPEC-009 EST-R08.1).
     */
    public function activeMemberByEmail(string $email): ?int;

    /**
     * The active members of the open institution holding this role, among
     * any others, by name. For other modules picking people (SPEC-008).
     *
     * @return list<array{id: int, name: string}>
     */
    public function activeMembersWithRole(string $role): array;

    /**
     * The names of these accounts, if they are or were members of the open
     * institution, and whether their membership is active (SPEC-008
     * EAC-R08.5). Accounts it can not see are left out.
     *
     * @param  list<int>  $accountIds
     * @return array<int, array{name: string, active: bool}>
     */
    public function accountNames(array $accountIds): array;

    /**
     * Pending invitations, newest first, optionally filtered by name or email.
     *
     * @return list<InvitationView>
     */
    public function pendingInvitations(?string $search): array;

    public function pendingInvitation(int $invitationId): ?InvitationView;
}
