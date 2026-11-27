<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\InstitutionDirectory;
use AulaX\Platform\Application\Port\InstitutionNotifier;
use AulaX\Platform\Application\Port\InstitutionUsage;

/**
 * The institutions, their usage and the notices, in memory.
 */
final class FakePlatformInstitutions implements InstitutionDirectory, InstitutionNotifier, InstitutionUsage
{
    /** @var array<int, array{id: int, name: string, subdomain: string, type: ?string, status: string, createdAt: string}> */
    public array $institutions = [];

    /** @var list<array{institution: int, active: bool, reason: string}> */
    public array $notices = [];

    /** @var list<int> */
    public array $read = [];

    public function add(int $id, string $name, string $status = 'active'): void
    {
        $this->institutions[$id] = ['id' => $id, 'name' => $name, 'subdomain' => strtolower(str_replace(' ', '-', $name)), 'type' => 'privada', 'status' => $status, 'createdAt' => '2026-01-10'];
    }

    public function page(?string $search, ?string $status, int $page): array
    {
        $items = array_values(array_filter($this->institutions, fn (array $institution) => $status === null || $institution['status'] === $status));

        return ['items' => $items, 'total' => count($items), 'page' => $page, 'perPage' => 50];
    }

    public function one(int $id): ?array
    {
        return $this->institutions[$id] ?? null;
    }

    public function setActive(int $id, bool $active): bool
    {
        $status = $active ? 'active' : 'inactive';
        $changed = $this->institutions[$id]['status'] !== $status;
        $this->institutions[$id]['status'] = $status;

        return $changed;
    }

    public function of(int $institutionId): array
    {
        $this->read[] = $institutionId;

        return ['members' => ['Administrador' => 1, 'Docente' => 12], 'activeStudents' => 340, 'lastActivity' => '2026-10-25T14:00:00+00:00', 'usedBytes' => 2048, 'activeYear' => '2026-2027'];
    }

    public function statusChanged(int $institutionId, bool $active, string $reason): void
    {
        $this->notices[] = ['institution' => $institutionId, 'active' => $active, 'reason' => $reason];
    }
}
