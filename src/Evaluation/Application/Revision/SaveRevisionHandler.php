<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Revision;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Gradebook\StudentScores;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\GradeChange;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\RevisionRepository;
use AulaX\Evaluation\Domain\SectionAudience;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * Whoever teaches a subject of Media General in a section records the
 * revisions of its failed final grades (RVP-R01): a grade, NP or none,
 * with the third term closed, in an open year, for the students in the
 * section whose result of the year is not recorded yet. All of a save is
 * checked before any revision changes; what changed is recorded (R07.1).
 */
final readonly class SaveRevisionHandler
{
    private EvaluationAccess $access;

    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private RevisionRepository $revisions,
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
        private EnrollmentFacts $enrollments,
        EvaluationPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws EvaluationNotFound
     */
    public function __invoke(SaveRevisionCommand $command): void
    {
        $section = $this->teaching->section($command->schoolYearId, $command->sectionId) ?? throw EvaluationNotFound::create();

        if ($section->level !== 'media_general' || ! in_array($command->subjectId, $this->teaching->curriculum($command->schoolYearId, $section->grade), true)) {
            throw EvaluationNotFound::create();
        }

        $this->access->ensureTeaches($command->sectionId, $command->subjectId);
        $thirdTermClosed = $this->calendar->termStatus($command->schoolYearId, 3) === 'closed';

        if ($thirdTermClosed && ! $this->calendar->gradesEditable($command->schoolYearId, 3, true)) {
            throw NotEditable::yearClosed();
        }

        $marks = [];

        foreach ($command->revisions as $studentId => $input) {
            $marks[(int) $studentId] = GradeMark::fromInput($input, "revisions.{$studentId}");
        }

        $studentIds = array_keys($marks);
        $periods = $this->enrollments->periods($command->schoolYearId, $studentIds);
        $audience = new SectionAudience($periods, $command->sectionId);
        $withResult = $this->enrollments->resultsRecorded($command->schoolYearId, $studentIds);
        $scores = new StudentScores($this->plans, $this->grades, $command->schoolYearId, $command->subjectId, $section->grade);
        $owner = PlanOwner::section($command->sectionId);
        $revisions = $this->revisions->revisionsOf($command->schoolYearId, $command->subjectId, $owner, $studentIds);
        $changes = [];

        foreach ($marks as $studentId => $mark) {
            if (! $audience->isCurrent($studentId, $this->clock->now())) {
                throw $audience->notCurrent($studentId);
            }

            $change = $revisions[$studentId]->record(
                $mark,
                $scores->final($studentId, $periods[$studentId] ?? []),
                $thirdTermClosed,
                in_array($studentId, $withResult, true),
            );

            if ($change !== null) {
                $changes[] = $change;
            }
        }

        if ($changes === []) {
            return;
        }

        $this->transactions->run(function () use ($revisions, $changes, $owner, $command) {
            $changed = array_map(fn (GradeChange $change) => $change->studentId, $changes);

            foreach ($changed as $studentId) {
                $this->revisions->saveRevision($revisions[$studentId]);
            }

            $this->audit->record(EvaluationAudit::revisionsSaved($owner, $command->subjectId, $changes));
        });
    }
}
