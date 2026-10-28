<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\NextYear;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\EnrollmentAudit;
use AulaX\Enrollment\Application\Placements;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Enrollment\Domain\Occupancy;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * A manager enrolls the chosen students of a year in the next one, each in
 * a section of the grade its result leads to (MAT-R07.4), with one reason
 * per section that goes over its capacity (MAT-R07.5). The proposal is
 * worked out again under the calendar lock; every enrollment is checked
 * before any is saved, so all happen or none (MAT-R07.4). Recorded once,
 * with how many and where (MAT-R12.1).
 */
final readonly class EnrollNextYearHandler
{
    private NextYearPlanner $planner;

    public function __construct(
        private EnrollmentRepository $enrollments,
        EnrollmentList $list,
        StudentFacts $students,
        StructureFacts $structure,
        private CalendarFacts $calendar,
        AgeRules $ageRules,
        private EnrollmentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {
        $this->planner = new NextYearPlanner($list, $students, $structure, $calendar, $ageRules, $permissions);
    }

    /**
     * @return int How many students were enrolled.
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    public function __invoke(EnrollNextYearCommand $command): int
    {
        EnrollmentAccess::ensureCanManage($this->permissions);

        return $this->transactions->run(function () use ($command) {
            $this->calendar->lock();
            $proposal = $this->planner->plan($command->fromSchoolYearId);
            $now = $this->clock->now();
            $taken = [];
            $enrollments = [];

            foreach ($command->placements as $studentId => $sectionId) {
                $entry = $proposal->entryOf((int) $studentId) ?? throw BusinessRuleViolation::onField('placements', $this->nameOf($proposal, (int) $studentId).' ya no está en la propuesta: vuelve a cargarla.');
                $section = $proposal->sectionFacts[(int) $sectionId] ?? null;

                if ($section === null || $section->grade !== $entry->toGrade) {
                    throw BusinessRuleViolation::onField('placements', "La sección elegida para {$entry->fullName} no es de {$entry->toGradeName}.");
                }

                $taken[$section->id] ??= $this->enrollments->currentCountIn($section->id);
                $overCapacityReason = (new Occupancy($section->capacity, $taken[$section->id]))->admit($command->overCapacityReasons[$section->id] ?? null);
                $taken[$section->id]++;
                $enrollments[] = Enrollment::enroll($entry->studentId, $proposal->toSchoolYearId, Placements::of($section), $now, $now, $overCapacityReason, null, $now, $command->actorId);
            }

            foreach ($enrollments as $enrollment) {
                $this->enrollments->save($enrollment);
            }

            $this->audit->record(EnrollmentAudit::enrolledInBatch($proposal->toSchoolYearId, count($enrollments), array_keys($taken)));

            return count($enrollments);
        });
    }

    private function nameOf(NextYearProposal $proposal, int $studentId): string
    {
        foreach ($proposal->excluded as $excluded) {
            if ($excluded->studentId === $studentId) {
                return $excluded->fullName;
            }
        }

        return 'El estudiante';
    }
}
