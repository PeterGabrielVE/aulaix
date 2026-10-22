<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Port;

/**
 * What Students asks IdentityAccess to give a guardian access (EST-R08.1):
 * whether the person already is a member of the open institution, and
 * inviting them as Representante otherwise.
 */
interface GuardianInvitations
{
    /**
     * The account id of the open institution's member with this email.
     */
    public function memberWithEmail(string $email): ?int;

    /**
     * Invites the email to the open institution with the Representante role.
     *
     * @return int The invitation's id.
     */
    public function invite(int $actorId, string $email, string $name): int;
}
