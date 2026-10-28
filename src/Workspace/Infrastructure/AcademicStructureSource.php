<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\AcademicStructure\Application\MyAssignments\MyAssignmentsQuery;
use AulaX\AcademicStructure\Application\Overview\CoordinatedGradesQuery;
use AulaX\AcademicStructure\Application\Overview\StructureOverviewQuery;
use AulaX\Enrollment\Application\Roster\EnrolledCountsQuery;
use AulaX\Workspace\Application\Port\StructureSource;

/**
 * Through AcademicStructure's public queries (A-06), which check
 * ver-estructura again (S-04); and Enrollment's, for the enrolled students
 * of those sections (SPEC-010).
 */
final readonly class AcademicStructureSource implements StructureSource
{
    public function __construct(
        private StructureOverviewQuery $overview,
        private CoordinatedGradesQuery $coordinatedGrades,
        private MyAssignmentsQuery $myAssignments,
        private EnrolledCountsQuery $enrolled,
    ) {}

    public function overview(?array $grades): ?array
    {
        $overview = ($this->overview)($grades);

        return $overview === null ? null : [
            'sections' => $overview->sections,
            'capacity' => $overview->capacity,
            'gradesWithoutSections' => count($overview->gradesWithoutSections),
            'sectionsWithoutGuide' => $overview->sectionsWithoutGuide,
            'subjectsWithoutTeacher' => $overview->subjectsWithoutTeacher,
            'assignmentsOfInactivePeople' => $overview->assignmentsOfInactivePeople,
            'enrolled' => $this->enrolled->total($overview->schoolYearId, $grades),
        ];
    }

    public function coordinatedGrades(int $accountId): array
    {
        return ($this->coordinatedGrades)($accountId);
    }

    public function classesOf(int $accountId): array
    {
        $assignments = ($this->myAssignments)($accountId);

        return [
            'hasActiveYear' => $assignments->schoolYearId !== null,
            'teaching' => array_map(fn (array $class) => ['section' => $class['section'], 'subject' => $class['subject']], $assignments->teaching),
            'guiding' => array_column($assignments->guiding, 'section'),
        ];
    }
}
