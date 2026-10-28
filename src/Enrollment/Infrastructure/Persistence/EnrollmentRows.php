<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure\Persistence;

use AulaX\Enrollment\Domain\AgeException;
use AulaX\Enrollment\Domain\Annulment;
use AulaX\Enrollment\Domain\AnnulmentReason;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\Period;
use AulaX\Enrollment\Domain\Placement;
use AulaX\Enrollment\Domain\PromotionResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Maps an enrollment to its row of `enrollments` and back. The level of the
 * grade is not stored: it follows from the grade.
 */
final class EnrollmentRows
{
    /**
     * @param  list<int>  $pendingSubjects  Of its result, from `enrollment_pending_subjects`.
     */
    public static function toDomain(object $row, array $pendingSubjects = []): Enrollment
    {
        return Enrollment::reconstitute(
            EnrollmentId::fromInt((int) $row->id),
            (int) $row->student_id,
            (int) $row->school_year_id,
            Placement::of((int) $row->section_id, $row->grade, self::levelOf($row->grade)),
            Period::reconstitute(
                new DateTimeImmutable($row->starts_on),
                $row->ends_on === null ? null : new DateTimeImmutable($row->ends_on),
                $row->end_reason === null ? null : EndReason::from($row->end_reason),
            ),
            $row->end_note,
            $row->over_capacity_reason,
            $row->age_exception_reason === null ? null : AgeException::reconstitute($row->age_exception_reason, (int) $row->age_exception_by),
            new DateTimeImmutable($row->registered_at),
            (int) $row->registered_by,
            $row->annulment_reason === null ? null : Annulment::reconstitute(
                AnnulmentReason::from($row->annulment_reason),
                $row->annulment_note,
                (int) $row->annulled_by,
                new DateTimeImmutable($row->annulled_at),
            ),
            $row->previous_id === null ? null : (int) $row->previous_id,
            $row->outcome === null ? null : PromotionResult::of(Outcome::from($row->outcome), $pendingSubjects),
            $row->outcome_recorded_at === null ? null : new DateTimeImmutable($row->outcome_recorded_at),
            $row->outcome_recorded_by === null ? null : (int) $row->outcome_recorded_by,
        );
    }

    /**
     * Rows of `enrollments` as enrollments, with their pending subjects read
     * in one query.
     *
     * @param  iterable<object>  $rows
     * @return list<Enrollment>
     */
    public static function manyToDomain(iterable $rows): array
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows, false);
        $pending = self::pendingSubjectsOf(array_map(fn (object $row) => (int) $row->id, $rows));

        return array_map(fn (object $row) => self::toDomain($row, $pending[(int) $row->id] ?? []), $rows);
    }

    /**
     * @param  list<int>  $enrollmentIds
     * @return array<int, list<int>> Enrollment id => subject ids.
     */
    public static function pendingSubjectsOf(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        $pending = [];

        foreach (DB::table('enrollment_pending_subjects')->whereIn('enrollment_id', $enrollmentIds)->orderBy('subject_id')->get(['enrollment_id', 'subject_id']) as $row) {
            $pending[(int) $row->enrollment_id][] = (int) $row->subject_id;
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toRow(Enrollment $enrollment): array
    {
        $period = $enrollment->period();
        $annulment = $enrollment->annulment();

        return [
            'student_id' => $enrollment->studentId(),
            'school_year_id' => $enrollment->schoolYearId(),
            'section_id' => $enrollment->placement()->sectionId,
            'grade' => $enrollment->placement()->grade,
            'starts_on' => $period->startsOn->format('Y-m-d'),
            'ends_on' => $period->endsOn?->format('Y-m-d'),
            'end_reason' => $period->endReason?->value,
            'end_note' => $enrollment->endNote(),
            'over_capacity_reason' => $enrollment->overCapacityReason(),
            'age_exception_reason' => $enrollment->ageException()?->justification,
            'age_exception_by' => $enrollment->ageException()?->approvedBy,
            'registered_at' => $enrollment->registeredAt()->format('Y-m-d H:i:sP'),
            'registered_by' => $enrollment->registeredBy(),
            'annulment_reason' => $annulment?->reason->value,
            'annulment_note' => $annulment?->note,
            'annulled_by' => $annulment?->annulledBy,
            'annulled_at' => $annulment?->annulledAt->format('Y-m-d H:i:sP'),
            'previous_id' => $enrollment->previousId(),
            'outcome' => $enrollment->result()?->outcome->value,
            'outcome_recorded_at' => $enrollment->resultRecordedAt()?->format('Y-m-d H:i:sP'),
            'outcome_recorded_by' => $enrollment->resultRecordedBy(),
        ];
    }

    private static function levelOf(string $grade): string
    {
        return match (true) {
            str_starts_with($grade, 'primaria_') => 'primaria',
            str_starts_with($grade, 'media_') => 'media_general',
            default => 'inicial',
        };
    }
}
