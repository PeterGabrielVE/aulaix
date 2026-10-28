<?php

namespace Tests\Fixtures\Evaluation;

use AulaX\Evaluation\Application\Port\SectionInfo;
use DateTimeImmutable;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * Everything the evaluation use cases need, in memory: school year 2 with
 * its first term open; section 10 of 3.er año (subjects 4, 5 and 6 in its
 * curriculum), 20 of 1.er grado and 30 of Preescolar; the teacher signed in
 * is account 9.
 */
final class EvaluationScene
{
    public InlineTransactionManager $transactions;

    public RecordingAuditTrail $audit;

    public FakeEvaluationPermissions $permissions;

    public FakeTeachingFacts $teaching;

    public FakeCalendarFacts $calendar;

    public FakeEnrollmentFacts $enrollments;

    public InMemoryEvaluationStore $store;

    public FixedClock $clock;

    public function __construct()
    {
        $this->transactions = new InlineTransactionManager;
        $this->audit = new RecordingAuditTrail($this->transactions);
        $this->permissions = new FakeEvaluationPermissions;
        $this->teaching = new FakeTeachingFacts;
        $this->calendar = new FakeCalendarFacts;
        $this->enrollments = new FakeEnrollmentFacts;
        $this->store = new InMemoryEvaluationStore;
        $this->clock = new FixedClock(new DateTimeImmutable('2026-10-20 10:00'));

        $this->teaching->sections = [
            10 => new SectionInfo(10, 2, 'media_3', '3.er año', 'media_general', 'A'),
            20 => new SectionInfo(20, 2, 'primaria_1', '1.er grado', 'primaria', 'A'),
            30 => new SectionInfo(30, 2, 'preescolar_2', '2.º nivel', 'inicial', 'A'),
        ];
        $this->teaching->curricula['2:media_3'] = [4, 5, 6];
    }
}
