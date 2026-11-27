<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\SuperAdminRules;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\NotFound;

/**
 * A SuperAdmin deactivates or reactivates another operator (PLT-R03.3).
 * Deactivating ends its sessions (PLT-R03.4) and revokes its pending links
 * (V4). Serialized under lockRoster() -> lockOperator() (ADR 0014, RSP-R01,
 * R02) and guarded by recent TOTP (RSP-NF03).
 */
final readonly class SetOperatorActiveHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorInvitationRepository $invitations,
        private OperatorLock $locks,
        private OperatorSession $session,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws NotFound
     */
    public function __invoke(OperatorId $target, bool $active): void
    {
        $actor = PlatformAccess::superAdminWithRecentTotp($this->session, $this->operators, $this->clock);
        $this->auditContext->actAsPlatformOperator($actor->id->value);

        $this->transactions->run(function () use ($actor, $target, $active) {
            // Fixed lock order (ADR 0014): roster first, then target operator.
            $this->locks->lockRoster();
            $this->locks->lockOperator($target);

            $operator = $this->operators->byId($target) ?? throw new NotFound('Ese operador no existe.');
            $from = $operator->status;

            if ($active === ($from !== OperatorStatus::Inactive)) {
                return;
            }

            SuperAdminRules::check($actor, $operator, demotes: ! $active, activeSuperAdmins: ActiveSuperAdmins::count($this->operators));

            // Re-verify the acting SuperAdmin under the roster lock so a concurrent
            // deactivation or demotion cannot be bypassed while waiting on the lock.
            PlatformAccess::superAdminWithRecentTotp($this->session, $this->operators, $this->clock);

            if ($active) {
                $operator->reactivate();
            } else {
                $operator->deactivate();
                $this->invitations->revokeAllOf($operator->id);
            }

            $this->operators->save($operator);
            $this->audit->recordForPlatform(PlatformAudit::activeChanged($operator->id, $active, $from->value, $operator->status->value));
        });
    }
}
