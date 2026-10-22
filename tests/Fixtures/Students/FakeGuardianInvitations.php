<?php

namespace Tests\Fixtures\Students;

use AulaX\Students\Application\Port\GuardianInvitations;

/**
 * Members known by email, and the invitations sent, in memory.
 */
final class FakeGuardianInvitations implements GuardianInvitations
{
    /** @var array<string, int> Email => account id. */
    public array $members = [];

    /** @var list<array{actor: int, email: string, name: string}> */
    public array $sent = [];

    public function memberWithEmail(string $email): ?int
    {
        return $this->members[$email] ?? null;
    }

    public function invite(int $actorId, string $email, string $name): int
    {
        $this->sent[] = ['actor' => $actorId, 'email' => $email, 'name' => $name];

        return 500 + count($this->sent);
    }
}
