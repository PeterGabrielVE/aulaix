<?php

namespace Tests\Fixtures\Students;

use AulaX\Students\Application\Port\GuardianInvitations;

/**
 * Members known by email, the roles granted and the invitations sent, in
 * memory.
 */
final class FakeGuardianInvitations implements GuardianInvitations
{
    /** @var array<string, int> Email => account id. */
    public array $members = [];

    /** @var list<array{actor: int, email: string, name: string}> */
    public array $sent = [];

    /** @var list<array{actor: int, member: int}> */
    public array $granted = [];

    public function memberWithEmail(string $email): ?int
    {
        return $this->members[$email] ?? null;
    }

    public function grantGuardianRole(int $actorId, int $memberId): void
    {
        $this->granted[] = ['actor' => $actorId, 'member' => $memberId];
    }

    public function invite(int $actorId, string $email, string $name): int
    {
        $this->sent[] = ['actor' => $actorId, 'email' => $email, 'name' => $name];

        return 500 + count($this->sent);
    }
}
