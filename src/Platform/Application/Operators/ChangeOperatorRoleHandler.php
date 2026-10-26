<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\SuperAdminRules;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\NotFound;

/**
 * A SuperAdmin changes another operator's role (PLT-R03.3); its open
 * sessions end (PLT-R03.4).
 */
final readonly class ChangeOperatorRoleHandler
{
    public function __construct(
        private OperatorRepository $operators,
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
    public function __invoke(OperatorId $target, OperatorRole $role): void
    {
        $actor = PlatformAccess::superAdmin($this->session, $this->operators);
        $this->auditContext->actAsPlatformOperator($actor->id->value);
        $operator = $this->operators->byId($target) ?? throw new NotFound('Ese operador no existe.');
        $from = $operator->role;

        if ($from === $role) {
            return;
        }

        SuperAdminRules::check($actor, $operator, demotes: $from === OperatorRole::SuperAdmin, activeSuperAdmins: ActiveSuperAdmins::count($this->operators));

        $this->transactions->run(function () use ($operator, $role, $from) {
            $operator->changeRole($role);
            $this->operators->save($operator);
            $this->audit->recordForPlatform(PlatformAudit::roleChanged($operator->id, $from->value, $role->value));
        });
    }
}
