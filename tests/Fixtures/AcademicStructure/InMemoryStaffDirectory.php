<?php

namespace Tests\Fixtures\AcademicStructure;

use AulaX\AcademicStructure\Application\Port\StaffDirectory;

/**
 * People of the institution with their roles and whether they are active,
 * kept in memory.
 */
final class InMemoryStaffDirectory implements StaffDirectory
{
    /** @var array<int, array{name: string, roles: list<string>, active: bool}> */
    public array $people = [];

    /**
     * @param  list<string>  $roles
     */
    public function add(int $id, string $name, array $roles, bool $active = true): void
    {
        $this->people[$id] = ['name' => $name, 'roles' => $roles, 'active' => $active];
    }

    public function isActiveWithRole(int $accountId, string $role): bool
    {
        $person = $this->people[$accountId] ?? null;

        return $person !== null && $person['active'] && in_array($role, $person['roles'], true);
    }

    public function candidates(string $role): array
    {
        $candidates = [];

        foreach ($this->people as $id => $person) {
            if ($person['active'] && in_array($role, $person['roles'], true)) {
                $candidates[] = ['id' => $id, 'name' => $person['name']];
            }
        }

        return $candidates;
    }

    public function describe(array $accountIds): array
    {
        $described = [];

        foreach ($accountIds as $id) {
            $described[$id] = isset($this->people[$id])
                ? ['name' => $this->people[$id]['name'], 'active' => $this->people[$id]['active']]
                : ['name' => 'Persona sin acceso', 'active' => false];
        }

        return $described;
    }
}
