<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\ChangePlacement;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\AgeAdmission;
use AulaX\Enrollment\Application\EnrollmentAudit;
use AulaX\Enrollment\Application\EnrollmentDate;
use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\Placements;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Enrollment\Domain\Occupancy;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\StudentNotFound;

/**
 * Moves a current enrollment to another section of its school year: the
 * enrollment ends on the date and the next one opens from it (MAT-R04.1,
 * R04.7), with the rules of capacity (MAT-R04.4) and, to another grade, of
 * age (MAT-R04.9). Under the calendar lock and recorded.
 */
final readonly class PlacementChange
{
    private Placements $placements;

    private AgeAdmission $ages;

    public function __construct(
        private EnrollmentRepository $enrollments,
        private StudentFacts $students,
        StructureFacts $structure,
        private CalendarFacts $calendar,
        AgeRules $ageRules,
        EnrollmentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {
        $this->placements = new Placements($structure, $calendar);
        $this->ages = new AgeAdmission($ageRules, $calendar, $permissions);
    }

    /**
     * @return int The id of the new enrollment.
     *
     * @throws BusinessRuleViolation
     * @throws EnrollmentNotFound
     */
    public function __invoke(ChangePlacementCommand $command, bool $toAnotherGrade): int
    {
        $date = EnrollmentDate::parse($command->date);

        return $this->transactions->run(function () use ($command, $date, $toAnotherGrade) {
            $this->calendar->lock();
            $enrollment = $command->enrollmentId > 0 ? $this->enrollments->byId(EnrollmentId::fromInt($command->enrollmentId)) : null;

            if ($enrollment === null) {
                throw EnrollmentNotFound::create();
            }

            $section = $this->placements->openSection($enrollment->schoolYearId(), $command->sectionId);
            $target = Placements::of($section);

            if ($toAnotherGrade && $target->sameGradeAs($enrollment->placement())) {
                throw BusinessRuleViolation::onField('section', 'La sección es del mismo grado: usa el cambio de sección.');
            }

            $overCapacityReason = (new Occupancy($section->capacity, $this->enrollments->currentCountIn($section->id)))->admit($command->overCapacityReason);
            $ageException = null;

            if ($toAnotherGrade) {
                $student = $this->students->byIds([$enrollment->studentId()])[$enrollment->studentId()] ?? throw StudentNotFound::create();
                $ageException = $this->ages->admit($student, $section, $command->ageExceptionJustification, $command->actorId)['exception'];
            }

            $now = $this->clock->now();
            $next = $enrollment->moveTo($target, $date, $command->reason, $now, $overCapacityReason, $ageException, $toAnotherGrade, $now, $command->actorId);
            $this->enrollments->save($enrollment);
            $id = $this->enrollments->save($next);
            $this->audit->record(EnrollmentAudit::moved($enrollment, $next));

            return $id->value;
        });
    }
}
