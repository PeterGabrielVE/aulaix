<?php

declare(strict_types=1);

namespace AulaX\Students\Application\GuardianAccount;

use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Events\MemberJoined;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Tenancy\Application\InstitutionContexts;
use AulaX\Tenancy\Application\TenantActivator;

/**
 * When an invitation sent for a guardian becomes a member, links that
 * account to the guardian (EST-R08.1). Runs in the institution of the
 * invitation, whatever institution is open, and leaves it as it found it.
 */
final readonly class LinkGuardianAccountHandler
{
    public function __construct(
        private GuardianRepository $guardians,
        private TenantActivator $activator,
        private InstitutionContexts $institutions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(MemberJoined $event): void
    {
        $institution = $this->institutions->of(InstitutionId::fromInt($event->institutionId));

        if ($institution === null) {
            return;
        }

        $this->activator->within($institution, fn () => $this->transactions->run(function () use ($event) {
            $guardian = $this->guardians->byPendingInvitation($event->invitationId);

            if ($guardian === null) {
                return;
            }

            $guardian->linkAccount($event->accountId);
            $this->guardians->save($guardian);
            $this->audit->record(StudentAudit::guardianLinked($guardian));
        }));
    }
}
