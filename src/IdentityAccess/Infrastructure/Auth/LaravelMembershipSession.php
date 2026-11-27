<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\MembershipSession;
use Illuminate\Contracts\Session\Session;

/**
 * Kept in the institution's own session, which ends with it.
 */
final readonly class LaravelMembershipSession implements MembershipSession
{
    private const string KEY = 'identity.active_institutions';

    public function __construct(private Session $session) {}

    public function rememberActiveInstitutions(int $count): void
    {
        $this->session->put(self::KEY, $count);
    }

    public function activeInstitutions(): ?int
    {
        $count = $this->session->get(self::KEY);

        return is_int($count) ? $count : null;
    }
}
