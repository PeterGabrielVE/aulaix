<?php

use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Identity\GuardianDocument;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Students\Domain\Student\Student;

/*
 * Helpers shared by the Students domain tests.
 */

function studentsToday(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-10-21');
}

function personalData(array $data = []): PersonalData
{
    $data += [
        'givenNames' => 'Ana Sofía', 'familyNames' => 'Pérez Mora', 'sex' => 'female', 'birthDate' => '2019-03-04',
        'nationality' => 'V', 'birthCountry' => null, 'birthStateId' => null, 'parishId' => null, 'address' => null,
        'emergencyPhone' => null,
    ];

    return PersonalData::fromInput(...$data, today: studentsToday());
}

function motherOf(string $cedula = 'V15432100', int $id = 1, bool $legal = true, bool $primary = true): Guardianship
{
    return new Guardianship(GuardianId::fromInt($id), GuardianDocument::fromInput('national_id', $cedula), Relationship::Mother, $legal, $primary);
}

function aStudent(?StudentIdentification $identification = null, ?array $guardianships = null, array $data = []): Student
{
    return Student::register(
        personalData($data),
        $identification ?? StudentIdentification::fromInput('school_id', 'V11915432100'),
        $guardianships ?? [motherOf()],
        HealthRecord::none(),
    );
}

/**
 * @return array<string, list<string>>
 */
function studentViolations(Closure $operation): array
{
    try {
        $operation();
    } catch (BusinessRuleViolations $violations) {
        return $violations->messagesByField();
    }

    test()->fail('The operation was accepted.');
}
