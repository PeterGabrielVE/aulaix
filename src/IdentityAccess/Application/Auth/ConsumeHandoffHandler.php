<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\BrowserBinding;
use AulaX\IdentityAccess\Application\Port\HandoffTokens;
use AulaX\IdentityAccess\Application\Port\MembershipSession;
use AulaX\Tenancy\Application\CurrentTenant;

/**
 * Starts the session of the institution's subdomain from a handoff token
 * issued on the central domain (IDG-R03.7, IDG-NF03): only once, within 60
 * seconds, for the institution it names and while the membership is active;
 * only in the browser that asked for it, and never over another account's
 * session (SPEC-015 RLT-R04).
 */
final readonly class ConsumeHandoffHandler
{
    public function __construct(
        private CurrentTenant $tenant,
        private HandoffTokens $tokens,
        private HasActiveMembershipHandler $hasActiveMembership,
        private Authenticator $authenticator,
        private MembershipSession $session,
        private BrowserBinding $browser,
    ) {}

    /**
     * @throws HandoffRejected
     */
    public function __invoke(#[\SensitiveParameter] string $token): void
    {
        $institutionId = $this->tenant->institutionId() ?? throw HandoffRejected::create();
        $browser = $this->browser->presented() ?? throw HandoffRejected::create();
        $grant = $this->tokens->consume($token, $institutionId, hash('sha256', $browser)) ?? throw HandoffRejected::create();

        if (! ($this->hasActiveMembership)($grant->account->value)) {
            throw HandoffRejected::create();
        }

        // RLT-R04.4: another account's session here is never replaced.
        $current = $this->authenticator->current();

        if ($current !== null && $current->value !== $grant->account->value) {
            throw HandoffRejected::create();
        }

        $this->authenticator->logIn($grant->account);
        $this->session->rememberActiveInstitutions($grant->activeInstitutions);
    }
}
