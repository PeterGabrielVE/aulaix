<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\PasswordResetTokens;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\NotFound;

/**
 * The owner asks to delete their account (IDG-R08.1 – R08.6), having typed
 * the password again (route) and confirmed the consequences. Unless
 * something blocks it, the account waits 30 days in pending_deletion: its
 * memberships are suspended, every session and pending token ends, and the
 * owner is told how to cancel.
 */
final readonly class RequestDeletionHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private DeletionBlockers $blockers,
        private AccountMemberships $memberships,
        private SessionRevoker $sessions,
        private PasswordResetTokens $resetTokens,
        private Authenticator $authenticator,
        private AccountMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    public function __invoke(int $accountId, bool $confirmed): void
    {
        if (! $confirmed) {
            throw BusinessRuleViolation::onField('confirmation', 'Confirma que entiendes que afectará tu acceso a todos los planteles asociados.');
        }

        $id = AccountId::fromInt($accountId);
        $account = $this->accounts->byId($id) ?? throw new NotFound('La cuenta no existe.');

        BusinessRuleViolations::throwIfAny(array_map(
            fn (string $reason) => BusinessRuleViolation::onField('confirmation', $reason),
            $this->blockers->reasonsFor($id),
        ));

        $pending = $account->requestDeletion($this->clock->now());

        $this->transactions->run(function () use ($id, $pending) {
            $this->memberships->suspendForDeletion($id);
            $this->accounts->save($pending);
            $this->resetTokens->delete($id);

            $this->audit->recordForPlatform(AuditEvent::of('identity.account.deletion_requested', AuditSubject::of('account', $id->value)));
        });

        $this->sessions->revokeAll($id);
        $this->authenticator->logOut();
        $this->mailer->deletionScheduled($pending);
    }
}
