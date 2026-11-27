<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use Collator;

/**
 * The groups of pending subjects of a school year, for whoever assigns
 * their teachers (RVP-R03.1, R03.2, R03.6): one per subject and grade of
 * origin that someone owes, with its teacher, or none, and its members.
 */
final readonly class PendingGroupsQuery
{
    public function __construct(
        private PendingMembers $members,
        private PendingGroupRepository $groups,
        private TeachingFacts $teaching,
        private EvaluationPermissions $permissions,
    ) {}

    /**
     * @return list<PendingGroupEntry> By grade of origin and subject.
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(int $schoolYearId): array
    {
        if (! $this->permissions->canAssignPendingTeachers()) {
            throw new AuthorizationDenied('No tienes permiso para gestionar los grupos de pendientes.');
        }

        $owed = $this->members->of($schoolYearId);
        $students = $this->members->students(array_values(array_unique(array_merge([], ...array_values($owed)))));
        $teachers = $this->teaching->activeTeachers();
        $names = $this->teaching->subjectNames('media_general');
        $grades = $this->teaching->gradeNames();
        $collator = new Collator('es');
        $entries = [];

        foreach ($owed as $key => $studentIds) {
            [$subjectId, $originGrade] = explode(':', $key);
            $group = $this->groups->groupOf($schoolYearId, (int) $subjectId, $originGrade);
            $members = array_map(fn (int $id) => [
                'studentId' => $id,
                'fullName' => $students[$id]->fullName ?? '',
                'identification' => $students[$id]->identification ?? '',
            ], $studentIds);
            usort($members, fn (array $a, array $b) => $collator->compare($a['fullName'], $b['fullName']));
            $teacher = $group?->teacherAccountId();

            $entries[] = new PendingGroupEntry(
                $group?->id(),
                (int) $subjectId,
                $names[(int) $subjectId] ?? '',
                $originGrade,
                $teacher,
                $teacher === null ? null : ($teachers[$teacher] ?? null),
                $members,
                $grades[$originGrade] ?? $originGrade,
            );
        }

        usort($entries, fn (PendingGroupEntry $a, PendingGroupEntry $b) => [$a->originGrade, $a->subjectId] <=> [$b->originGrade, $b->subjectId]);

        return $entries;
    }

    /**
     * Who may teach a group: the active members with the role Docente
     * (RVP-R03.5), by name.
     *
     * @return list<array{id: int, name: string}>
     *
     * @throws AuthorizationDenied
     */
    public function teachers(): array
    {
        if (! $this->permissions->canAssignPendingTeachers()) {
            throw new AuthorizationDenied('No tienes permiso para gestionar los grupos de pendientes.');
        }

        $teachers = [];

        foreach ($this->teaching->activeTeachers() as $id => $name) {
            $teachers[] = ['id' => $id, 'name' => $name];
        }

        return $teachers;
    }
}
