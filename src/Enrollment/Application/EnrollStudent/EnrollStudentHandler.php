<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\EnrollStudent;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\AgeAdmission;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\EnrollmentAudit;
use AulaX\Enrollment\Application\EnrollmentDate;
use AulaX\Enrollment\Application\Placements;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\AlreadyEnrolled;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Enrollment\Domain\NotEnrollable;
use AulaX\Enrollment\Domain\Occupancy;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\StudentNotFound;

/**
 * A manager enrolls a student in a section of a school year (MAT-R01): over
 * the capacity only with a reason (MAT-R02), out of the age range only with
 * a directive's exception (MAT-R03). Under the calendar lock (ADR 0007, so
 * two managers never take the last place together) and recorded in the
 * audit log (MAT-R12.1).
 */
final readonly class EnrollStudentHandler
{
    private Placements $placements;

    private AgeAdmission $ages;

    public function __construct(
        private EnrollmentRepository $enrollments,
        private StudentFacts $students,
        StructureFacts $structure,
        private CalendarFacts $calendar,
        AgeRules $ageRules,
        private EnrollmentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {
        $this->placements = new Placements($structure, $calendar);
        $this->ages = new AgeAdmission($ageRules, $calendar, $permissions);
    }

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws AlreadyEnrolled
     * @throws StudentNotFound
     * @throws SchoolYearNotFound
     */
    public function __invoke(EnrollStudentCommand $command): EnrollmentResult
    {
        EnrollmentAccess::ensureCanManage($this->permissions);
        $startsOn = EnrollmentDate::parse($command->date);

        return $this->transactions->run(function () use ($command, $startsOn) {
            $this->calendar->lock();

            if ($this->calendar->statusOf($command->schoolYearId) === null) {
                throw SchoolYearNotFound::create();
            }

            $student = $this->students->byIds([$command->studentId])[$command->studentId] ?? throw StudentNotFound::create();
            $section = $this->placements->openSection($command->schoolYearId, $command->sectionId);

            if (! $student->active) {
                throw NotEnrollable::withdrawn();
            }

            $current = $this->enrollments->currentOf($student->id, $command->schoolYearId);

            if ($current !== null) {
                $where = $this->placements->section($command->schoolYearId, $current->placement()->sectionId);

                throw AlreadyEnrolled::in($where?->gradeName, $where?->letter);
            }

            $overCapacityReason = (new Occupancy($section->capacity, $this->enrollments->currentCountIn($section->id)))->admit($command->overCapacityReason);
            $age = $this->ages->admit($student, $section, $command->ageExceptionJustification, $command->actorId);
            $now = $this->clock->now();

            $enrollment = Enrollment::enroll(
                $student->id,
                $command->schoolYearId,
                Placements::of($section),
                $startsOn,
                $now,
                $overCapacityReason,
                $age['exception'],
                $now,
                $command->actorId,
            );
            $id = $this->enrollments->save($enrollment);
            $this->audit->record(EnrollmentAudit::enrolled($enrollment));

            return new EnrollmentResult($id->value, $age['warning']);
        });
    }
}
