<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Pending;

use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * What a term still lacks, by teacher, section, subject and activity (or
 * literal or report), with how many students (EVA-R12.1), for the managers
 * who close it.
 */
final readonly class PendingGradesQuery
{
    public function __construct(
        private PendingGrades $pending,
        private TeachingFacts $teaching,
        private EvaluationPermissions $permissions,
    ) {}

    /**
     * @throws AuthorizationDenied
     */
    public function __invoke(int $schoolYearId, int $term): PendingGradesView
    {
        if (! $this->permissions->canView() || ! $this->permissions->seesAll()) {
            throw new AuthorizationDenied('No tienes permiso para ver las notas pendientes.');
        }

        $items = $this->pending->of($schoolYearId, $term);

        if ($items === []) {
            return new PendingGradesView(0, []);
        }

        $staff = $this->teaching->staffNames($schoolYearId);
        $teachers = $this->teaching->activeTeachers();
        $rows = array_map(fn (PendingItem $item) => new PendingGradesRow(
            match (true) {
                $item->ofPendingGroup => $item->teacherAccountId === null ? null : ($teachers[$item->teacherAccountId] ?? null),
                $item->subjectId === null => $staff['guides'][$item->sectionId] ?? null,
                default => $staff['teachers'][$item->sectionId][$item->subjectId] ?? null,
            },
            $item->section,
            $item->subject,
            match ($item->kind) {
                PendingItem::ACTIVITY => (string) $item->activity?->name,
                PendingItem::NO_PLAN => 'Sin plan de evaluación',
                PendingItem::LITERAL => 'Literal',
                default => 'Informe',
            },
            count($item->students),
        ), $items);

        return new PendingGradesView(array_sum(array_map(fn (PendingGradesRow $row) => $row->students, $rows)), $rows);
    }
}
