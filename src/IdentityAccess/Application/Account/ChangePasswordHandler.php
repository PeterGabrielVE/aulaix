<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\NotFound;

/**
 * The owner changes their password, for every institution (IDG-R06.1),
 * knowing the current one (IAM-R07.4). Every other session is closed, the
 * owner is told by email (IDG-R06.1, R06.2) and the change is recorded
 * without the password (IDG-R10.2).
 */
final readonly class ChangePasswordHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private Authenticator $authenticator,
        private PasswordHasher $hasher,
        private SessionRevoker $sessions,
        private AccountMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(ChangePasswordCommand $command): void
    {
        $id = AccountId::fromInt($command->accountId);
        $account = $this->accounts->byId($id) ?? throw new NotFound('La cuenta no existe.');

        if (! $this->authenticator->passwordMatches($id, $command->currentPassword)) {
            throw BusinessRuleViolation::onField('current_password', 'La contraseña actual no es correcta.');
        }

        $this->transactions->run(function () use ($account, $command) {
            $this->accounts->save($account->changePassword($this->hasher->hash($command->newPassword)));
            $this->audit->recordForPlatform(AuditEvent::of('identity.account.password_changed', AuditSubject::of('account', $account->id->value)));
        });

        $this->sessions->revokeOthers($id);
        $this->mailer->passwordChanged($account);
    }
}
