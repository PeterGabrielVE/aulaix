<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\Enrollment\Application\GradedEnrollments;
use AulaX\Enrollment\Application\Port\GradedEnrollment;
use DateTimeImmutable;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * Everything the enrollment use cases need, in memory: an active school
 * year 2026-2027 (id 2) with two sections of 3.er grado (10 and 11) and one
 * of 4.º grado (20), a student, permissions, the clock and the audit log.
 */
final class EnrollmentScene
{
    public InlineTransactionManager $transactions;

    public RecordingAuditTrail $audit;

    public InMemoryEnrollmentRepository $enrollments;

    public FakeStudentFacts $students;

    public FakeStructureFacts $structure;

    public FakeCalendarFacts $calendar;

    public FakeAgeRules $ages;

    public FakeEnrollmentPermissions $permissions;

    public FixedClock $clock;

    /** @var list<int> Enrollment ids with grades, as SPEC-011 would tell. */
    public array $graded = [];

    public function __construct()
    {
        $this->transactions = new InlineTransactionManager;
        $this->audit = new RecordingAuditTrail($this->transactions);
        $this->enrollments = new InMemoryEnrollmentRepository;
        $this->students = new FakeStudentFacts;
        $this->structure = new FakeStructureFacts;
        $this->calendar = new FakeCalendarFacts($this->transactions);
        $this->ages = new FakeAgeRules;
        $this->permissions = new FakeEnrollmentPermissions;
        $this->clock = new FixedClock(new DateTimeImmutable('2026-10-15 09:00'));

        $this->calendar->year(2, 2026);
        $this->structure->section(10, 2, 'primaria_3', 'A', 30);
        $this->structure->section(11, 2, 'primaria_3', 'B', 30);
        $this->structure->section(20, 2, 'primaria_4', 'A', 30);
        // 8 years old on December 31, 2026: the regular age of 3.er grado.
        $this->students->add(5, '2018-03-10');
    }

    public function graded(): GradedEnrollments
    {
        $scene = $this;

        return new GradedEnrollments([new class($scene) implements GradedEnrollment
        {
            public function __construct(private readonly EnrollmentScene $scene) {}

            public function hasGrades(int $enrollmentId): bool
            {
                return in_array($enrollmentId, $this->scene->graded, true);
            }
        }]);
    }
}
