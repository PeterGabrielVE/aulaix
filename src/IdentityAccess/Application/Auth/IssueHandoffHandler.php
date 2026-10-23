<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Application\Port\HandoffTokens;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Shared\Domain\NotFound;

/**
 * Lets a person authenticated on the central domain into one of their
 * institutions without asking for the password again (IDG-R03.7): a token
 * for that institution's subdomain, only if their membership there is
 * active. Any other institution gets the answer of one that does not exist
 * (IDG-R03.9).
 */
final readonly class IssueHandoffHandler
{
    public function __construct(
        private AccountInstitutions $institutions,
        private HandoffTokens $tokens,
    ) {}

    /**
     * @return string The token, for the subdomain's handoff route.
     *
     * @throws NotFound
     */
    public function __invoke(int $accountId, string $subdomain): string
    {
        $account = AccountId::fromInt($accountId);

        $institutions = $this->institutions->activeFor($account);

        foreach ($institutions as $institution) {
            if ($institution->subdomain === $subdomain) {
                // SPEC-013 NAV-R02.5: only here are all of them visible (ADR 0006).
                return $this->tokens->issue($account, InstitutionId::fromInt($institution->id), count($institutions));
            }
        }

        throw new NotFound('El plantel no existe.');
    }
}
