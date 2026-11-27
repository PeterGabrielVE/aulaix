<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\ChangeSet;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\PromotionResult;

/**
 * The audit log entries of enrollments (MAT-R12.1): what was enrolled and
 * where, with the reasons and who approved an exception. Never the
 * student's health data (MAT-R12.2).
 */
final class EnrollmentAudit
{
    public static function enrolled(Enrollment $enrollment): AuditEvent
    {
        return AuditEvent::of('enrollment.enrollment.enrolled', self::subject($enrollment), ChangeSet::diff([], [
            'student_id' => $enrollment->studentId(),
            'school_year_id' => $enrollment->schoolYearId(),
            'section_id' => $enrollment->placement()->sectionId,
            'grade' => $enrollment->placement()->grade,
            'starts_on' => $enrollment->period()->startsOn->format('Y-m-d'),
            'over_capacity_reason' => $enrollment->overCapacityReason(),
            'age_exception' => $enrollment->ageException()?->justification,
            'age_exception_by' => $enrollment->ageException()?->approvedBy,
        ]));
    }

    /**
     * A change of section or grade, under the new enrollment.
     */
    public static function moved(Enrollment $previous, Enrollment $next): AuditEvent
    {
        $changesGrade = $previous->period()->endReason === EndReason::GradeChange;

        return AuditEvent::of(
            $changesGrade ? 'enrollment.enrollment.grade_changed' : 'enrollment.enrollment.section_changed',
            self::subject($next),
            ChangeSet::diff(
                ['section_id' => $previous->placement()->sectionId, 'grade' => $previous->placement()->grade],
                [
                    'section_id' => $next->placement()->sectionId,
                    'grade' => $next->placement()->grade,
                    'date' => $next->period()->startsOn->format('Y-m-d'),
                    'reason' => $previous->endNote(),
                    'over_capacity_reason' => $next->overCapacityReason(),
                    'age_exception' => $changesGrade ? $next->ageException()?->justification : null,
                    'approved_by' => $changesGrade ? $next->registeredBy() : null,
                ],
            ),
        );
    }

    /**
     * Ended by a withdrawal or a transfer (MAT-R05).
     */
    public static function ended(Enrollment $enrollment): AuditEvent
    {
        return AuditEvent::of('enrollment.enrollment.ended', self::subject($enrollment), ChangeSet::diff([], [
            'ends_on' => $enrollment->period()->endsOn?->format('Y-m-d'),
            'end_reason' => $enrollment->period()->endReason?->value,
        ]));
    }

    public static function annulled(Enrollment $enrollment): AuditEvent
    {
        return AuditEvent::of('enrollment.enrollment.annulled', self::subject($enrollment), ChangeSet::diff([], [
            'reason' => $enrollment->annulment()?->reason->value,
            'note' => $enrollment->annulment()?->note,
        ]));
    }

    /**
     * The result of the year, and the one it replaced (MAT-R06.8).
     */
    public static function resultRecorded(?PromotionResult $before, Enrollment $enrollment): AuditEvent
    {
        return AuditEvent::of('enrollment.enrollment.result_recorded', self::subject($enrollment), ChangeSet::diff(
            ['outcome' => $before?->outcome->value, 'pending_subjects' => $before === null || $before->pendingSubjects === [] ? null : $before->pendingSubjects],
            ['outcome' => $enrollment->result()?->outcome->value, 'pending_subjects' => $enrollment->result()?->pendingSubjects ?: null],
        ));
    }

    /**
     * The enrollment of a year's students in the next one (MAT-R07, R12.1).
     *
     * @param  list<int>  $sectionIds
     */
    public static function enrolledInBatch(int $schoolYearId, int $students, array $sectionIds): AuditEvent
    {
        sort($sectionIds);

        return AuditEvent::of('enrollment.batch.enrolled', AuditSubject::of('school_year', $schoolYearId), ChangeSet::diff([], [
            'students' => $students,
            'sections' => $sectionIds,
        ]));
    }

    private static function subject(Enrollment $enrollment): AuditSubject
    {
        return AuditSubject::of('enrollment', (int) $enrollment->id()?->value);
    }
}
