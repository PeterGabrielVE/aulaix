<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

final readonly class JoinInstitutionCommand
{
    /**
     * @param  int  $accountId  The logged-in account accepting.
     * @param  string  $subdomain  The inviting institution's, unvalidated.
     * @param  ?string  $invitedEmail  The email the link was sent to, when accepting from the link.
     */
    public function __construct(
        public int $accountId,
        public string $subdomain,
        public ?string $invitedEmail = null,
    ) {}
}
