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
     * Pending invitations, newest first, optionally filtered by name or email.
     *
     * @return list<InvitationView>
     */
    public function pendingInvitations(?string $search): array;

    public function pendingInvitation(int $invitationId): ?InvitationView;
}
