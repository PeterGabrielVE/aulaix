<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Hooks;

use AulaX\AcademicCalendar\Application\Port\TermClosingGuard;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Application\Pending\PendingGrades;
use AulaX\Evaluation\Application\Pending\PendingItem;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\MarkChange;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use AulaX\Shared\Domain\Clock;

/**
 * A term does not close with grades, literals or reports missing (EVA-R12.2).
 * Closed by exception, the missing grades of Media General become NP and
 * the literals and reports incomplete, each recorded, and the justification
 * goes to the audit log (EVA-R12.3). Runs inside the closing's transaction,
 * under the calendar lock.
 */
final readonly class PendingGradesGuard implements TermClosingGuard
{
    public function __construct(
        private PendingGrades $pending,
        private ActivityGradesRepository $grades,
        private StudentMarksRepository $marks,
        private EnrollmentFacts $enrollments,
        private Clock $clock,
        private AuditTrail $audit,
    ) {}

    public function pendingGrades(int $schoolYearId, int $term): int
    {
        return array_sum(array_map(fn (PendingItem $item) => count($item->students), $this->pending->of($schoolYearId, $term)));
    }

    public function fillPending(int $schoolYearId, int $term, string $justification): void
    {
        $filled = 0;

        foreach ($this->pending->of($schoolYearId, $term) as $item) {
            $filled += match ($item->kind) {
                PendingItem::ACTIVITY => $this->fillGrades($schoolYearId, $item),
                PendingItem::LITERAL => $this->fillMarks($schoolYearId, $term, $item, MarkKind::Literal),
                PendingItem::REPORT => $this->fillMarks($schoolYearId, $term, $item, MarkKind::Report),
                // A subject without a plan has no grade to fill.
                default => 0,
            };
        }

        $this->audit->record(AuditEvent::of('evaluation.term.closed_by_exception', AuditSubject::of('school_year', $schoolYearId), [
            FieldChange::of('term', null, $term),
            FieldChange::of('justification', null, $justification),
            FieldChange::of('filled', null, $filled),
        ]));
    }

    private function fillGrades(int $schoolYearId, PendingItem $item): int
    {
        $activity = $item->activity;

        if ($activity === null || $activity->id === null) {
            return 0;
        }

        $grades = $this->grades->forActivity($activity, $item->sectionId);
        $changes = $grades->record(
            array_fill_keys($item->students, GradeMark::notPresented()),
            $this->enrollments->periods($schoolYearId, $item->students),
            $this->clock->now(),
        );
        $this->grades->saveGrades($grades);
        $this->audit->record(EvaluationAudit::gradesSaved($activity->id->value, $changes, false));

        return count($changes);
    }

    private function fillMarks(int $schoolYearId, int $term, PendingItem $item, MarkKind $kind): int
    {
        $filled = 0;
        $field = ($kind === MarkKind::Literal ? 'literal_' : 'report_').$term;

        foreach ($this->marks->forStudents($schoolYearId, $item->students) as $studentId => $marks) {
            if ($marks->fillIncomplete((string) $term, $kind)) {
                $this->marks->saveMarks($marks);
                $this->audit->record(EvaluationAudit::marksSaved($studentId, [new MarkChange($field, null, 'incompleto')], false));
                $filled++;
            }
        }

        return $filled;
    }
}
