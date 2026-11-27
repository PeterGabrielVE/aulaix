<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\SuperAdminRules;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\NotFound;

/**
 * A SuperAdmin deactivates or reactivates another operator (PLT-R03.3).
 * Deactivating ends its sessions (PLT-R03.4) and revokes its pending links
 * (V4).
 */
final readonly class SetOperatorActiveHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorInvitationRepository $invitations,
        private OperatorSession $session,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws NotFound
     */
    public function __invoke(OperatorId $target, bool $active): void
    {
        $actor = PlatformAccess::superAdmin($this->session, $this->operators);
        $this->auditContext->actAsPlatformOperator($actor->id->value);
        $operator = $this->operators->byId($target) ?? throw new NotFound('Ese operador no existe.');
        $from = $operator->status;

        if ($active === ($from !== OperatorStatus::Inactive)) {
            return;
        }

        SuperAdminRules::check($actor, $operator, demotes: ! $active, activeSuperAdmins: ActiveSuperAdmins::count($this->operators));

        $this->transactions->run(function () use ($operator, $active, $from) {
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
