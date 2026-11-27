<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\FieldChange;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeChange;
use AulaX\Evaluation\Domain\MarkChange;
use AulaX\Evaluation\Domain\PlanOwner;

/**
 * The audit log entries of evaluation (EVA-R10.1): the plan before and
 * after, and each grade that changed with its old and new value, flagged
 * when the term was closed (EVA-R06.3).
 */
final class EvaluationAudit
{
    /**
     * @param  list<Activity>  $before
     */
    public static function planChanged(EvaluationPlan $plan, array $before): AuditEvent
    {
        return AuditEvent::of('evaluation.plan.changed', AuditSubject::of('evaluation_plan', (int) $plan->id()), [
            FieldChange::of('activities', $before === [] ? null : self::describe($before), self::describe($plan->activities())),
        ]);
    }

    /**
     * @param  list<GradeChange>  $changes
     */
    public static function gradesSaved(int $activityId, array $changes, bool $closedTerm): AuditEvent
    {
        $fields = array_map(fn (GradeChange $change) => FieldChange::of("student_{$change->studentId}", $change->before, $change->after), $changes);

        if ($closedTerm) {
            $fields[] = FieldChange::of('closed_term', null, true);
        }

        return AuditEvent::of('evaluation.activity.grades_saved', AuditSubject::of('activity', $activityId), $fields);
    }

    /**
     * The revisions that changed in a subject of a section or of a group of
     * pending subjects, with their old and new value (RVP-R07.1).
     *
     * @param  list<GradeChange>  $changes
     */
    public static function revisionsSaved(PlanOwner $owner, int $subjectId, array $changes): AuditEvent
    {
        $subject = $owner->isSection()
            ? AuditSubject::of('section', (int) $owner->sectionId)
            : AuditSubject::of('pending_group', (int) $owner->pendingGroupId);

        return AuditEvent::of('evaluation.revisions.saved', $subject, [
            FieldChange::of('subject', null, $subjectId),
            ...array_map(fn (GradeChange $change) => FieldChange::of("student_{$change->studentId}", $change->before, $change->after), $changes),
        ]);
    }

    /**
     * The teacher of a group of pending subjects, before and after (RVP-R07.1).
     */
    public static function pendingTeacherAssigned(int $groupId, ?int $before, ?int $after): AuditEvent
    {
        return AuditEvent::of('evaluation.pending_group.teacher_assigned', AuditSubject::of('pending_group', $groupId), [
            FieldChange::of('teacher', $before, $after),
        ]);
    }

    /**
     * A literal, final literal or report recorded or changed; of a report,
     * only that it was written (EVA-R10.2).
     *
     * @param  list<MarkChange>  $changes
     */
    public static function marksSaved(int $studentId, array $changes, bool $closedTerm): AuditEvent
    {
        $fields = array_map(fn (MarkChange $change) => FieldChange::of($change->field, $change->before, $change->after), $changes);

        if ($closedTerm) {
            $fields[] = FieldChange::of('closed_term', null, true);
        }

        return AuditEvent::of('evaluation.student.marks_saved', AuditSubject::of('student', $studentId), $fields);
    }

    /**
     * @param  list<Activity>  $activities
     * @return list<string>
     */
    private static function describe(array $activities): array
    {
        return array_map(fn (Activity $activity) => "{$activity->name} · {$activity->date->format('d/m/Y')} · {$activity->weight->display()} %", $activities);
    }
}
