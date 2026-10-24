<?php

namespace Tests\Fixtures\Workspace;

use AulaX\Workspace\Application\Port\ActivitySource;
use AulaX\Workspace\Application\Port\InstitutionSource;
use AulaX\Workspace\Application\Port\StaffSource;
use AulaX\Workspace\Application\Port\StructureSource;
use AulaX\Workspace\Application\Port\StudentSource;

/**
 * Every source of the home screen in memory, recording which ones were asked
 * (NAV-R10.2: a block left out of the plan is never fetched).
 */
final class FakeHomeSources implements ActivitySource, InstitutionSource, StaffSource, StructureSource, StudentSource
{
    /** @var list<string> */
    public array $asked = [];

    /** @var ?array<string, int> */
    public ?array $overview = [
        'sections' => 3, 'capacity' => 90, 'gradesWithoutSections' => 2, 'sectionsWithoutGuide' => 1,
        'subjectsWithoutTeacher' => 0, 'assignmentsOfInactivePeople' => 1,
    ];

    /** @var ?list<string> */
    public ?array $overviewGrades = null;

    /** @var array<int, list<string>> */
    public array $coordinated = [];

    public array $classes = ['hasActiveYear' => true, 'teaching' => [['section' => '1.er grado A', 'subject' => 'Matemática']], 'guiding' => []];

    public int $students = 120;

    public array $requests = ['transfers' => 1, 'corrections' => 0];

    public array $represented = ['students' => [['id' => 9, 'name' => 'Ana Pérez']], 'pendingCorrections' => 1];

    public array $staff = ['Administrador' => 1, 'Director' => 1, 'Coordinador' => 2, 'Docente' => 12, 'Representante' => 80, 'Estudiante' => 0];

    public array $invitationCounts = ['pending' => 2, 'expired' => 1];

    public array $missing = ['dea_code'];

    public array $activity = [['occurredAt' => '2026-10-23 10:00', 'actor' => 'Ana Rivas', 'event' => 'students.student.registered']];

    public function overview(?array $grades): ?array
    {
        $this->asked[] = 'overview';
        $this->overviewGrades = $grades;

        return $this->overview;
    }

    public function coordinatedGrades(int $accountId): array
    {
        $this->asked[] = 'coordinatedGrades';

        return $this->coordinated[$accountId] ?? [];
    }

    public function classesOf(int $accountId): array
    {
        $this->asked[] = 'classes';

        return $this->classes;
    }

    public function activeStudents(): int
    {
        $this->asked[] = 'students';

        return $this->students;
    }

    public function pendingRequests(): array
    {
        $this->asked[] = 'requests';

        return $this->requests;
    }

    public function representedBy(int $accountId): array
    {
        $this->asked[] = 'represented';

        return $this->represented;
    }

    public function membersByRole(): array
    {
        $this->asked[] = 'staff';

        return $this->staff;
    }

    public function invitations(): array
    {
        $this->asked[] = 'invitations';

        return $this->invitationCounts;
    }

    public function gaps(): array
    {
        $this->asked[] = 'gaps';

        return $this->missing;
    }

    public function recent(int $limit): array
    {
        $this->asked[] = 'activity';

        return array_slice($this->activity, 0, $limit);
    }
}
