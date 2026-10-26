<?php

namespace Tests\Fixtures\Tenancy;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\Subdomain;
use Throwable;

/**
 * Institutions kept in memory, keyed by id.
 */
final class InMemoryInstitutionRepository implements InstitutionRepository
{
    /** @var array<int, Institution> */
    public array $institutions = [];

    /** Thrown by the next save(), to simulate a failing transaction. */
    public ?Throwable $failOnSave = null;

    public function __construct(Institution ...$institutions)
    {
        foreach ($institutions as $institution) {
            $this->institutions[$institution->id->value] = $institution;
        }
    }

    public function findActiveBySubdomain(Subdomain $subdomain): ?Institution
    {
        foreach ($this->institutions as $institution) {
            if ($institution->subdomain->value === $subdomain->value && $institution->isActive()) {
                return $institution;
            }
        }

        return null;
    }

    public function byId(InstitutionId $id): ?Institution
    {
        return $this->institutions[$id->value] ?? null;
    }

    public function saveStatus(Institution $institution): void
    {
        $this->institutions[$institution->id->value] = $institution;
    }

    public function save(Institution $institution): void
    {
        if ($this->failOnSave !== null) {
            throw $this->failOnSave;
        }

        $this->institutions[$institution->id->value] = $institution;
    }

    public function isDeaCodeTaken(string $deaCode, InstitutionId $except): bool
    {
        return $this->isTaken(fn (Institution $institution) => $institution->profile->deaCode === $deaCode, $except);
    }

    public function isRifTaken(string $rif, InstitutionId $except): bool
    {
        return $this->isTaken(fn (Institution $institution) => $institution->profile->rif === $rif, $except);
    }

    /**
     * @param  callable(Institution): bool  $matches
     */
    private function isTaken(callable $matches, InstitutionId $except): bool
    {
        foreach ($this->institutions as $id => $institution) {
            if ($id !== $except->value && $matches($institution)) {
                return true;
            }
        }

        return false;
    }
}
