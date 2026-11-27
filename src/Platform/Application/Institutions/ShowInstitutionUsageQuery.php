<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Institutions;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAccess;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\InstitutionDirectory;
use AulaX\Platform\Application\Port\InstitutionUsage;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\NotFound;

/**
 * One institution and its usage, for any operator (SPEC-014 PLT-R04.3,
 * R04.4). The query itself goes to the platform log (PLT-R07.1), written
 * after the bounded read has given the institution back (V1).
 */
final readonly class ShowInstitutionUsageQuery
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorSession $session,
        private InstitutionDirectory $institutions,
        private InstitutionUsage $usage,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @return array{institution: array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}, usage: array{members: array<string, int>, activeStudents: int, lastActivity: ?string, usedBytes: int, activeYear: ?string}}
     *
     * @throws AuthorizationDenied
     * @throws NotFound
     */
    public function __invoke(int $institutionId): array
    {
        $operator = PlatformAccess::operator($this->session, $this->operators);
        $institution = $this->institutions->one($institutionId) ?? throw new NotFound('Ese plantel no existe.');
        $usage = $this->usage->of($institutionId);

        $this->auditContext->actAsPlatformOperator($operator->id->value);
        $this->transactions->run(fn () => $this->audit->recordForPlatform(PlatformAudit::institutionViewed($institutionId)));

        return ['institution' => $institution, 'usage' => $usage];
    }
}
