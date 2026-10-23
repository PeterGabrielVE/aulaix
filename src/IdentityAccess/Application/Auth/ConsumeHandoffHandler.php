<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\HandoffTokens;
use AulaX\IdentityAccess\Application\Port\MembershipSession;
use AulaX\Tenancy\Application\CurrentTenant;

/**
 * Starts the session of the institution's subdomain from a handoff token
 * issued on the central domain (IDG-R03.7, IDG-NF03): only once, within 60
 * seconds, for the institution it names and while the membership is active.
 */
final readonly class ConsumeHandoffHandler
{
    public function __construct(
        private CurrentTenant $tenant,
        private HandoffTokens $tokens,
        private HasActiveMembershipHandler $hasActiveMembership,
        private Authenticator $authenticator,
        private MembershipSession $session,
    ) {}

    /**
     * @throws HandoffRejected
     */
    public function __invoke(#[\SensitiveParameter] string $token): void
    {
        $institutionId = $this->tenant->institutionId() ?? throw HandoffRejected::create();
        $grant = $this->tokens->consume($token, $institutionId) ?? throw HandoffRejected::create();

        if (! ($this->hasActiveMembership)($grant->account->value)) {
            throw HandoffRejected::create();
        }

        $this->authenticator->logIn($grant->account);
        $this->session->rememberActiveInstitutions($grant->activeInstitutions);
    }
}
