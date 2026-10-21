<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\UpdateInstitution;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\ChangeSet;
use AulaX\Catalogs\Application\EducationLevels;
use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\InstitutionAudit;
use AulaX\Tenancy\Application\OpenInstitution;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionLevels;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InstitutionType;

/**
 * The manager saves the details, type and levels of the open institution
 * (INS-R01 – R03). Every broken rule is reported at once; only the fields
 * that changed reach the audit log (INS-R06.1).
 */
final readonly class UpdateInstitutionHandler
{
    public function __construct(
        private InstitutionRepository $institutions,
        private CurrentTenant $tenant,
        private InstitutionPermissions $permissions,
        private EducationLevels $educationLevels,
        private GeographyDirectory $geography,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws BusinessRuleViolations
     */
    public function __invoke(UpdateInstitutionCommand $command): void
    {
        $id = OpenInstitution::forManagement($this->tenant, $this->permissions);

        $this->transactions->run(function () use ($id, $command) {
            $institution = OpenInstitution::load($this->institutions, $id);
            $updated = $this->apply($institution, $command);

            $changes = ChangeSet::diff(InstitutionAudit::details($institution), InstitutionAudit::details($updated));

            if ($changes === []) {
                return;
            }

            $this->institutions->save($updated);
            $this->audit->record(AuditEvent::of('tenancy.institution.updated', InstitutionAudit::subject($updated), $changes));
        });
    }

    /**
     * @throws BusinessRuleViolations
     */
    private function apply(Institution $institution, UpdateInstitutionCommand $command): Institution
    {
        $violations = [];
        $attempt = function (callable $change) use (&$violations, &$institution): void {
            try {
                $institution = $change($institution);
            } catch (BusinessRuleViolations $broken) {
                array_push($violations, ...$broken->violations());
            } catch (BusinessRuleViolation $broken) {
                $violations[] = $broken;
            }
        };

        $attempt(fn (Institution $current) => $current->rename($command->name));
        $attempt(fn (Institution $current) => $current->changeProfile(InstitutionProfile::fromInput(
            $command->deaCode, $command->rif, $command->parishId, $command->address, $command->phone, $command->email,
        )));
        $attempt(fn (Institution $current) => $current->changeType(InstitutionType::fromInput($command->type)));
        $attempt(fn (Institution $current) => $current->changeLevels($this->levels($command->levels)));

        $violations = [...$violations, ...$this->platformViolations($institution, $command)];

        BusinessRuleViolations::throwIfAny($violations);

        return $institution;
    }

    /**
     * @param  list<string>  $codes
     */
    private function levels(array $codes): InstitutionLevels
    {
        $levels = InstitutionLevels::of($codes);

        foreach ($levels->codes() as $code) {
            if (! $this->educationLevels->exists($code)) {
                throw BusinessRuleViolation::onField('levels', 'Elige niveles del catálogo.');
            }
        }

        return $levels;
    }

    /**
     * Rules that look beyond the institution: the other institutions' DEA
     * codes and RIFs (without saying whose, INS-R01.3) and the catalog's
     * parishes (INS-R01.5).
     *
     * @return list<BusinessRuleViolation>
     */
    private function platformViolations(Institution $institution, UpdateInstitutionCommand $command): array
    {
        $violations = [];
        $profile = $institution->profile;

        if ($profile->deaCode !== null && $this->institutions->isDeaCodeTaken($profile->deaCode, $institution->id)) {
            $violations[] = BusinessRuleViolation::onField('dea_code', 'Ese código DEA ya está registrado.');
        }

        if ($profile->rif !== null && $this->institutions->isRifTaken($profile->rif, $institution->id)) {
            $violations[] = BusinessRuleViolation::onField('rif', 'Ese RIF ya está registrado.');
        }

        if ($command->parishId !== null && ! $this->geography->parishExists($command->parishId)) {
            $violations[] = BusinessRuleViolation::onField('parish_id', 'Elige una parroquia del catálogo.');
        }

        return $violations;
    }
}
