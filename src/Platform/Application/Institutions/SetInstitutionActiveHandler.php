<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Institutions;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\InstitutionDirectory;
use AulaX\Platform\Application\Port\InstitutionNotifier;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\NotFound;

/**
 * A SuperAdmin deactivates or reactivates an institution (SPEC-014
 * PLT-R05): nothing is deleted, deactivating needs a reason, and the change
 * is recorded in the same transaction. Its Administradores are told
 * afterwards, so a mail failure never undoes the switch.
 */
final readonly class SetInstitutionActiveHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private InstitutionDirectory $institutions,
        private InstitutionNotifier $notifier,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws NotFound
     */
    public function __invoke(int $institutionId, bool $active, string $reason): void
    {
        $operator = PlatformAccess::superAdmin($this->session, $this->operators);
        $this->auditContext->actAsPlatformOperator($operator->id->value);
        $reason = trim($reason);

        if (! $active && $reason === '') {
            throw BusinessRuleViolation::onField('reason', 'Indica el motivo.');
        }

        if (mb_strlen($reason) > 500) {
            throw BusinessRuleViolation::onField('reason', 'El motivo admite como máximo 500 caracteres.');
        }

        $this->institutions->one($institutionId) ?? throw new NotFound('Ese plantel no existe.');

        $changed = $this->transactions->run(function () use ($institutionId, $active, $reason) {
            if (! $this->institutions->setActive($institutionId, $active)) {
                return false;
            }

            $this->audit->recordForPlatform(PlatformAudit::institutionStatusChanged($institutionId, $active, $reason));

            return true;
        });

        if ($changed) {
            $this->notifier->statusChanged($institutionId, $active, $reason);
        }
    }
}
