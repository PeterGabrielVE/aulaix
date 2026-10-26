<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Administration;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Shared\Domain\NotFound;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InstitutionStatus;

/**
 * The platform activates or deactivates an institution (SPEC-014 PLT-R05.1,
 * R05.4, R05.5): only its status changes, nothing is deleted. Only Platform
 * calls it, after checking its operator (an architecture test forbids
 * anyone else); it runs in the caller's transaction.
 */
final readonly class SetInstitutionStatusHandler
{
    public function __construct(private InstitutionRepository $institutions) {}

    /**
     * @return bool Whether the status changed.
     *
     * @throws NotFound
     */
    public function __invoke(InstitutionId $id, bool $active): bool
    {
        $institution = $this->institutions->byId($id) ?? throw new NotFound('Ese plantel no existe.');
        $status = $active ? InstitutionStatus::Active : InstitutionStatus::Inactive;

        if ($institution->status === $status) {
            return false;
        }

        $this->institutions->saveStatus($institution->withStatus($status));

        return true;
    }
}
