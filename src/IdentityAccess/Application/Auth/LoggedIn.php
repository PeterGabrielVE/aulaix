<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

final readonly class LoggedIn
{
    /**
     * @param  list<InstitutionChoice>  $institutions  Where the person can go now.
     */
    public function __construct(
        public int $accountId,
        public array $institutions,
    ) {}
}
