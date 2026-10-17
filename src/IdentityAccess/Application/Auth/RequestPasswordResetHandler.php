<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\PasswordResetTokens;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\InvalidEmailAddress;

/**
 * "Forgot your password?" on the central domain (IDG-R06.3). It returns
 * nothing: the person always gets the same answer, whether the email has an
 * account or not, or a link was already sent a moment ago (IAM-R05.3,
 * R05.4).
 *
 * An email still shared by several historical accounts has no single
 * password to reset: it gets guidance to consolidate them instead.
 */
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private PasswordResetTokens $tokens,
        private AccountMailer $mailer,
    ) {}

    public function __invoke(string $email): void
    {
        try {
            $accounts = $this->accounts->byEmail(EmailAddress::fromString($email));
        } catch (InvalidEmailAddress) {
            return;
        }

        if ($accounts === []) {
            return;
        }

        $account = $accounts[0];

        if ($account->isUnderConsolidation()) {
            $this->mailer->consolidationGuidance($account);

            return;
        }

        if (! $account->isActive() || $this->tokens->recentlyCreated($account->id)) {
            return;
        }

        $this->mailer->passwordResetLink($account, $this->tokens->create($account->id));
    }
}
