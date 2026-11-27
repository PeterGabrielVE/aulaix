<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\EmailChangeRequest;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\NotFound;

/**
 * The owner asks to change their email (IDG-R05.2): a verification link goes
 * to the new address and the current email stays until it is used.
 *
 * An address that already has an account gets a notice instead, and the
 * requester the same answer as always, so the form can not be used to find
 * out which emails are registered (IDG-R05.4).
 */
final readonly class RequestEmailChangeHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountMailer $mailer,
        private Clock $clock,
    ) {}

    public function __invoke(int $accountId, string $newEmail): void
    {
        $account = $this->accounts->byId(AccountId::fromInt($accountId)) ?? throw new NotFound('La cuenta no existe.');
        $email = EmailAddress::fromString($newEmail);

        if (! $email->equals($account->email) && $this->accounts->byEmail($email) !== []) {
            $this->mailer->emailAlreadyInUse($email);

            return;
        }

        $issued = EmailChangeRequest::issue($email, $this->clock->now());
        $this->accounts->save($account->requestEmailChange($issued->request));

        $this->mailer->emailChangeVerification($account, $email, $issued->token);
    }
}
