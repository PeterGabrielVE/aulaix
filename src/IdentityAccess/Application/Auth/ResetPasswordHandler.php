<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Application\Port\PasswordResetTokens;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\InvalidEmailAddress;
use AulaX\Shared\Application\TransactionManager;

/**
 * Sets a new password from a reset link: for the whole account, in every
 * institution (IDG-R06.1). Every session of the account is closed, the owner
 * is told by email (IDG-R06.1, R06.2), and the change is recorded in the
 * platform log without the password (IDG-R10.2).
 */
final readonly class ResetPasswordHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private PasswordResetTokens $tokens,
        private PasswordHasher $hasher,
        private SessionRevoker $sessions,
        private AccountMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws PasswordResetRejected
     */
    public function __invoke(ResetPasswordCommand $command): void
    {
        try {
            $accounts = $this->accounts->byEmail(EmailAddress::fromString($command->email));
        } catch (InvalidEmailAddress) {
            throw PasswordResetRejected::create();
        }

        $account = count($accounts) === 1 ? $accounts[0] : null;

        if ($account === null
            || $account->isUnderConsolidation()
            || ! $account->isActive()
            || ! $this->tokens->isValid($account->id, $command->token)) {
            throw PasswordResetRejected::create();
        }

        $this->transactions->run(function () use ($account, $command) {
            $this->accounts->save($account->changePassword($this->hasher->hash($command->password)));
            $this->tokens->delete($account->id);

            $this->audit->recordForPlatform(AuditEvent::of('identity.account.password_changed', AuditSubject::of('account', $account->id->value)));
        });

        $this->sessions->revokeAll($account->id);
        $this->mailer->passwordChanged($account);
    }
}
