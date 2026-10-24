<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailChangeNotUsable;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Clock;

/**
 * The link from the new address was opened: that email is now the
 * account's, already verified, and the previous address is told
 * (IDG-R05.3). If someone took the address meanwhile, the change is refused.
 */
final readonly class ConfirmEmailChangeHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EmailChangeNotUsable
     */
    public function __invoke(#[\SensitiveParameter] string $token): void
    {
        $account = $this->accounts->byPendingEmailToken($token) ?? throw EmailChangeNotUsable::create();
        $confirmed = $account->confirmEmailChange($token, $this->clock->now());

        if ($this->accounts->byEmail($confirmed->email) !== []) {
            throw EmailChangeNotUsable::create();
        }

        $this->transactions->run(function () use ($confirmed) {
            $this->accounts->save($confirmed);
            $this->audit->recordForPlatform(AuditEvent::of('identity.account.email_changed', AuditSubject::of('account', $confirmed->id->value)));
        });

        $this->mailer->emailChanged($account);
    }
}
