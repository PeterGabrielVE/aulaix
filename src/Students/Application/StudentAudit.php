<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\ChangeSet;
use AulaX\Audit\Application\FieldChange;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\Withdrawal;

/**
 * The audit log entries of the Students module (EST-R10): a student's data
 * and guardians with their old and new values; its health data only by the
 * name of what changed, never by value (EST-R10.3, NF02).
 */
final class StudentAudit
{
    public static function registered(Student $student): AuditEvent
    {
        return AuditEvent::of('students.student.registered', self::subject($student), ChangeSet::diff([], self::details($student)));
    }

    /**
     * @param  array<string, mixed>  $before  self::details() of the student before the change.
     */
    public static function updated(array $before, Student $student): AuditEvent
    {
        return AuditEvent::of('students.student.updated', self::subject($student), ChangeSet::diff($before, self::details($student)));
    }

    public static function healthChanged(HealthRecord $before, HealthRecord $after, Student $student): AuditEvent
    {
        $changes = [];

        foreach ($after->fields() as $field => $value) {
            if ($before->fields()[$field] !== $value) {
                $changes[] = FieldChange::of($field, null, null);
            }
        }

        return AuditEvent::of('students.student.health_changed', self::subject($student), $changes);
    }

    public static function withdrawn(Student $student, Withdrawal $withdrawal): AuditEvent
    {
        return AuditEvent::of('students.student.withdrawn', self::subject($student), [
            FieldChange::of('status', 'active', 'withdrawn'),
            FieldChange::of('withdrawn_on', null, $withdrawal->withdrawnOn->format('Y-m-d')),
            FieldChange::of('reason', null, $withdrawal->reason->value),
        ]);
    }

    public static function reinstated(Student $student): AuditEvent
    {
        return AuditEvent::of('students.student.reinstated', self::subject($student), [FieldChange::of('status', 'withdrawn', 'active')]);
    }

    /**
     * @param  array<string, mixed>  $before  self::guardianDetails() before the change.
     */
    public static function guardianUpdated(array $before, Guardian $guardian): AuditEvent
    {
        return AuditEvent::of(
            'students.guardian.updated',
            AuditSubject::of('guardian', (int) $guardian->id()?->value),
            ChangeSet::diff($before, self::guardianDetails($guardian)),
        );
    }

    /**
     * The student as form fields, its guardians as id:relationship:legal:primary.
     *
     * @return array<string, mixed>
     */
    public static function details(Student $student): array
    {
        $data = $student->personalData();

        return [
            'given_names' => $data->givenNames,
            'family_names' => $data->familyNames,
            'sex' => $data->sex->value,
            'birth_date' => $data->birthDate->format('Y-m-d'),
            'nationality' => $data->nationality,
            'identification_type' => $student->identification()->type,
            'identification' => $student->identification()->value(),
            'birth_country' => $data->birthCountry,
            'birth_state_id' => $data->birthStateId,
            'parish_id' => $data->parishId,
            'address' => $data->address,
            'emergency_phone' => $data->emergencyPhone,
            'guardians' => array_map(
                fn (Guardianship $link) => implode(':', [
                    $link->guardianId->value,
                    $link->relationship->value,
                    $link->isLegal ? 'legal' : 'not_legal',
                    $link->isPrimary ? 'primary' : 'secondary',
                ]),
                $student->guardianships(),
            ),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public static function guardianDetails(Guardian $guardian): array
    {
        $data = $guardian->data();

        return [
            'given_names' => $data->givenNames,
            'family_names' => $data->familyNames,
            'document_type' => $data->document->type,
            'document' => $data->document->value(),
            'phone' => $data->phone,
            'email' => $data->email,
            'address' => $data->address,
        ];
    }

    private static function subject(Student $student): AuditSubject
    {
        return AuditSubject::of('student', (int) $student->id()?->value);
    }
}
