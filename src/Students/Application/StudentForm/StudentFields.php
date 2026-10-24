<?php

declare(strict_types=1);

namespace AulaX\Students\Application\StudentForm;

/**
 * The student form as typed: its data, its identification and its guardians.
 */
final readonly class StudentFields
{
    /**
     * @param  list<GuardianFields>  $guardians
     */
    public function __construct(
        public ?string $givenNames,
        public ?string $familyNames,
        public ?string $sex,
        public ?string $birthDate,
        public ?string $nationality,
        public ?string $identificationType,
        public ?string $identification,
        public ?string $birthCountry,
        public ?int $birthStateId,
        public ?int $parishId,
        public ?string $address,
        public ?string $emergencyPhone,
        public array $guardians,
    ) {}
}
