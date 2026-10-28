<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure\Persistence;

use AulaX\Enrollment\Domain\AgeException;
use AulaX\Enrollment\Domain\Annulment;
use AulaX\Enrollment\Domain\AnnulmentReason;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Period;
use AulaX\Enrollment\Domain\Placement;
use DateTimeImmutable;

/**
 * Maps an enrollment to its row of `enrollments` and back. The level of the
 * grade is not stored: it follows from the grade.
 */
final class EnrollmentRows
{
    public static function toDomain(object $row): Enrollment
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
        );
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
