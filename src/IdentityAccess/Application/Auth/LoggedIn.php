<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

final readonly class LoggedIn
{
    /**
     * @param  list<InstitutionChoice>  $institutions  Where the person can go now.
     * @param  bool  $pendingDeletion  Only the cancel-deletion screen is open to them (IDG-R08.7).
     */
    public function __construct(
        public int $accountId,
        public array $institutions,
        public bool $pendingDeletion = false,
    ) {}
}
