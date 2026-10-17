<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\LoginThrottle;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\InvalidEmailAddress;

/**
 * The central login (IDG-R03.1). An email awaiting consolidation may still
 * have several accounts, one per institution: the password is tried against
 * each, and the one it matches opens only its own institution (IDG-R09.3).
 *
 * Five failures per email and IP lock that pair out for a minute, even with
 * the right password (IDG-R03.10).
 */
final readonly class LogInHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountInstitutions $institutions,
        private Authenticator $authenticator,
        private LoginThrottle $throttle,
    ) {}

    /**
     * @throws LoginFailed
     */
    public function __invoke(LogInCommand $command): LoggedIn
    {
        $lockedOutFor = $this->throttle->lockedOutFor($command->email, $command->ip);

        if ($lockedOutFor !== null) {
            throw LoginFailed::tooManyAttempts($lockedOutFor);
        }

        $account = $this->accountMatching($command);

        if ($account === null) {
            $this->throttle->recordFailure($command->email, $command->ip);

            throw LoginFailed::invalidCredentials();
        }

        $this->throttle->clear($command->email, $command->ip);

        if (! $account->isActive()) {
            throw LoginFailed::accountNotActive();
        }

        $this->authenticator->logIn($account->id);

        return new LoggedIn($account->id->value, $this->institutions->activeFor($account->id));
    }

    private function accountMatching(LogInCommand $command): ?Account
    {
        try {
            $email = EmailAddress::fromString($command->email);
        } catch (InvalidEmailAddress) {
            return null;
        }

        foreach ($this->accounts->byEmail($email) as $account) {
            if ($this->authenticator->passwordMatches($account->id, $command->password)) {
                return $account;
            }
        }

        return null;
    }
}
