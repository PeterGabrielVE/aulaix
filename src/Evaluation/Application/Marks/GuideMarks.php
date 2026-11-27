<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\MarkChange;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\MarkNotValid;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Evaluation\Domain\StudentMarks;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * What recording a literal or a report has in common (EVA-R04, R05): a
 * section of the right level, its guide (EVA-R07.2), a student currently in
 * it, a term that takes changes (EVA-R06), and the audit log of what changed
 * (EVA-R10.1), flagged when the term was closed (EVA-R06.3).
 */
final readonly class GuideMarks
{
    private EvaluationAccess $access;

    public function __construct(
        private StudentMarksRepository $marks,
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
     * @param  ?int  $term  Null for the final literal, which needs the third term closed.
     * @param  callable(StudentMarks, bool): list<MarkChange>  $change  Gets the marks and whether the third term is closed.
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws EvaluationNotFound
     */
    public function record(int $schoolYearId, int $sectionId, int $studentId, MarkKind $kind, ?int $term, callable $change): void
    {
        $section = $this->teaching->section($schoolYearId, $sectionId) ?? throw EvaluationNotFound::create();

        if ($section->level !== $kind->level()) {
            throw MarkNotValid::wrongLevel($kind);
        }

        $this->access->ensureGuides($sectionId);
        $thirdTermClosed = $this->calendar->termStatus($schoolYearId, 3) === 'closed';
        $closedTerm = $term === null ? $this->ensureFinalEditable($schoolYearId, $thirdTermClosed) : $this->access->ensureEditable($schoolYearId, $term);
        $periods = $this->enrollments->periods($schoolYearId, [$studentId])[$studentId] ?? [];

        if (! EnrollmentPeriod::currentIn($periods, $sectionId, $this->clock->now())) {
            throw MarkNotValid::notInSection();
        }

        $this->transactions->run(function () use ($schoolYearId, $studentId, $change, $thirdTermClosed, $closedTerm) {
            $marks = $this->marks->forStudents($schoolYearId, [$studentId])[$studentId];
            $changes = $change($marks, $thirdTermClosed);

            if ($changes === []) {
                return;
            }

            $this->marks->saveMarks($marks);
            $this->audit->record(EvaluationAudit::marksSaved($studentId, $changes, $closedTerm));
        });
    }

    /**
     * The final literal goes after the third term closes, without the
     * special permission, never once the year is closed (CAL-R05.3).
     */
    private function ensureFinalEditable(int $schoolYearId, bool $thirdTermClosed): bool
    {
        if ($thirdTermClosed && ! $this->calendar->gradesEditable($schoolYearId, 3, true)) {
            throw NotEditable::yearClosed();
        }

        return false;
    }
}
