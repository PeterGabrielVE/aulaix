<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use AulaX\Shared\Domain\Conflict;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Identity\GuardianDocument;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Students\Domain\Student\Sex;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Student\StudentStatus;
use AulaX\Students\Domain\Student\Withdrawal;
use AulaX\Students\Domain\Student\WithdrawalReason;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentStudentRepository implements StudentRepository
{
    private const string UNIQUE_VIOLATION = '23505';

    public function byId(StudentId $id): ?Student
    {
        $model = StudentModel::query()->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(Student $student): StudentId
    {
        try {
            return DB::transaction(fn () => $this->write($student));
        } catch (QueryException $exception) {
            if ($exception->getCode() === self::UNIQUE_VIOLATION) {
                throw new Conflict('Ya hay un estudiante con esa identificación en el plantel.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function isIdentificationTaken(StudentIdentification $identification, ?StudentId $except): bool
    {
        return StudentModel::query()
            ->where('identification', $identification->value())
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->value))
            ->exists();
    }

    private function write(Student $student): StudentId
    {
        $data = $student->personalData();
        $attributes = [
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
            'allergies' => $student->health()->allergies,
            'medical_conditions' => $student->health()->medicalConditions,
            'disability' => $student->health()->disability,
            'status' => $student->status()->value,
        ];

        $model = $student->id() === null
            ? StudentModel::query()->create($attributes)
            : tap(StudentModel::query()->findOrFail($student->id()->value))->update($attributes);
        $id = (int) $model->id;

        // The children are replaced whole: the aggregate is their only writer.
        foreach (['guardianships', 'student_withdrawals', 'student_previous_identifications'] as $table) {
            DB::table($table)->where('student_id', $id)->delete();
        }

        DB::table('guardianships')->insert(array_map(fn (Guardianship $link) => [
            'student_id' => $id,
            'guardian_id' => $link->guardianId->value,
            'relationship' => $link->relationship->value,
            'is_legal' => $link->isLegal,
            'is_primary' => $link->isPrimary,
        ], $student->guardianships()));

        DB::table('student_withdrawals')->insert(array_map(fn (Withdrawal $withdrawal) => [
            'student_id' => $id,
            'withdrawn_on' => $withdrawal->withdrawnOn->format('Y-m-d'),
            'reason' => $withdrawal->reason->value,
            'note' => $withdrawal->note,
            'reinstated_on' => $withdrawal->reinstatedOn?->format('Y-m-d'),
        ], $student->withdrawals()));

        DB::table('student_previous_identifications')->insert(array_map(fn (string $schoolId) => [
            'student_id' => $id,
            'identification' => $schoolId,
            'replaced_on' => now()->toDateString(),
        ], $student->previousSchoolIds()));

        return StudentId::fromInt($id);
    }

    private function toDomain(StudentModel $model): Student
    {
        $id = (int) $model->id;

        $guardianships = DB::table('guardianships')
            ->join('guardians', 'guardians.id', '=', 'guardianships.guardian_id')
            ->where('guardianships.student_id', $id)
            ->orderBy('guardianships.id')
            ->get(['guardianships.*', 'guardians.document_type', 'guardians.document'])
            ->map(fn (object $row) => new Guardianship(
                GuardianId::fromInt((int) $row->guardian_id),
                GuardianDocument::fromInput($row->document_type, $row->document),
                Relationship::from($row->relationship),
                (bool) $row->is_legal,
                (bool) $row->is_primary,
            ))
            ->all();

        $withdrawals = DB::table('student_withdrawals')
            ->where('student_id', $id)
            ->orderBy('withdrawn_on')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => new Withdrawal(
                new DateTimeImmutable($row->withdrawn_on),
                WithdrawalReason::from($row->reason),
                $row->note,
                $row->reinstated_on === null ? null : new DateTimeImmutable($row->reinstated_on),
            ))
            ->all();

        $previous = DB::table('student_previous_identifications')
            ->where('student_id', $id)
            ->orderBy('id')
            ->pluck('identification')
            ->all();

        return Student::reconstitute(
            StudentId::fromInt($id),
            PersonalData::reconstitute(
                $model->given_names,
                $model->family_names,
                Sex::from($model->sex),
                new DateTimeImmutable((string) $model->birth_date),
                $model->nationality,
                $model->birth_country,
                $model->birth_state_id === null ? null : (int) $model->birth_state_id,
                $model->parish_id === null ? null : (int) $model->parish_id,
                $model->address,
                $model->emergency_phone,
            ),
            StudentIdentification::fromInput($model->identification_type, $model->identification),
            $previous,
            $guardianships,
            HealthRecord::fromInput($model->allergies, $model->medical_conditions, $model->disability),
            StudentStatus::from($model->status),
            $withdrawals,
        );
    }
}
