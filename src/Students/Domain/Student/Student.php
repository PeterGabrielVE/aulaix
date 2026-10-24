<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Domain\Identity\SchoolId;
use AulaX\Students\Domain\Identity\StudentIdentification;
use DateTimeImmutable;

/**
 * A student of the institution (SPEC-009): its personal data, its
 * identification, its guardians, its health data and its withdrawals.
 *
 * The cédula escolar is checked against the birth year and the guardians'
 * cédulas when it is assigned, and again when a guardian is unlinked
 * (EST-R02.4, R02.5, R03.7). A guardian's cédula corrected later does not
 * invalidate it.
 */
final class Student
{
    /**
     * @param  list<string>  $previousSchoolIds
     * @param  list<Guardianship>  $guardianships
     * @param  list<Withdrawal>  $withdrawals
     */
    private function __construct(
        private ?StudentId $id,
        private PersonalData $personalData,
        private StudentIdentification $identification,
        private array $previousSchoolIds,
        private array $guardianships,
        private HealthRecord $health,
        private StudentStatus $status,
        private array $withdrawals,
    ) {}

    /**
     * @param  list<Guardianship>  $guardianships
     *
     * @throws BusinessRuleViolations
     */
    public static function register(PersonalData $personalData, StudentIdentification $identification, array $guardianships, HealthRecord $health): self
    {
        BusinessRuleViolations::throwIfAny([
            ...self::guardianshipViolations($guardianships),
            ...self::schoolIdViolations($identification->schoolId(), $personalData, $guardianships),
        ]);

        return new self(null, $personalData, $identification, [], array_values($guardianships), $health, StudentStatus::Active, []);
    }

    /**
     * @param  list<string>  $previousSchoolIds
     * @param  list<Guardianship>  $guardianships
     * @param  list<Withdrawal>  $withdrawals
     */
    public static function reconstitute(
        StudentId $id,
        PersonalData $personalData,
        StudentIdentification $identification,
        array $previousSchoolIds,
        array $guardianships,
        HealthRecord $health,
        StudentStatus $status,
        array $withdrawals,
    ): self {
        return new self($id, $personalData, $identification, $previousSchoolIds, $guardianships, $health, $status, $withdrawals);
    }

    public function id(): ?StudentId
    {
        return $this->id;
    }

    public function identify(StudentId $id): void
    {
        $this->id = $id;
    }

    public function personalData(): PersonalData
    {
        return $this->personalData;
    }

    public function identification(): StudentIdentification
    {
        return $this->identification;
    }

    /**
     * The cédulas escolares it had before its cédula de identidad (EST-R02.7).
     *
     * @return list<string>
     */
    public function previousSchoolIds(): array
    {
        return $this->previousSchoolIds;
    }

    /**
     * @return list<Guardianship>
     */
    public function guardianships(): array
    {
        return $this->guardianships;
    }

    public function health(): HealthRecord
    {
        return $this->health;
    }

    public function status(): StudentStatus
    {
        return $this->status;
    }

    /**
     * @return list<Withdrawal> Oldest first.
     */
    public function withdrawals(): array
    {
        return $this->withdrawals;
    }

    /**
     * New data, identification and guardians, with the rules of the
     * registration (EST-R01.5). Moving from a cédula escolar to a cédula de
     * identidad keeps the former in the history (EST-R02.7).
     *
     * @param  list<Guardianship>  $guardianships
     *
     * @throws BusinessRuleViolations
     */
    public function update(PersonalData $personalData, StudentIdentification $identification, array $guardianships): void
    {
        $schoolId = $identification->schoolId();
        $identificationChanged = ! $identification->equals($this->identification);
        $violations = self::guardianshipViolations($guardianships);

        if ($identificationChanged) {
            $violations = [...$violations, ...self::schoolIdViolations($schoolId, $personalData, $guardianships)];
        } elseif ($schoolId !== null) {
            if ($personalData->birthDate->format('Y') !== $this->personalData->birthDate->format('Y')) {
                $violations = [...$violations, ...self::yearViolations($schoolId, $personalData)];
            }

            if ($this->unlinksGuardianOf($schoolId, $guardianships)) {
                $violations[] = BusinessRuleViolation::onField('guardians', 'No puedes quitar al representante cuya cédula forma la cédula escolar.');
            }
        }

        BusinessRuleViolations::throwIfAny($violations);

        $previous = $this->identification->schoolId();

        if ($previous !== null && $schoolId === null) {
            $this->previousSchoolIds[] = $previous->value;
        }

        $this->personalData = $personalData;
        $this->identification = $identification;
        $this->guardianships = array_values($guardianships);
    }

    public function changeHealth(HealthRecord $health): void
    {
        $this->health = $health;
    }

    /**
     * @throws BusinessRuleViolation When it is already withdrawn.
     */
    public function withdraw(Withdrawal $withdrawal): void
    {
        if ($this->status === StudentStatus::Withdrawn) {
            throw BusinessRuleViolation::onField('status', 'El estudiante ya está retirado.');
        }

        $this->status = StudentStatus::Withdrawn;
        $this->withdrawals[] = $withdrawal;
    }

    /**
     * Back to active, its withdrawals kept (EST-R06.4).
     *
     * @throws BusinessRuleViolation When it is active.
     */
    public function reinstate(DateTimeImmutable $today): void
    {
        if ($this->status === StudentStatus::Active) {
            throw BusinessRuleViolation::onField('status', 'El estudiante ya está activo.');
        }

        $last = array_key_last($this->withdrawals);

        if ($last !== null) {
            $this->withdrawals[$last] = $this->withdrawals[$last]->reinstatedOn($today);
        }

        $this->status = StudentStatus::Active;
    }

    /**
     * At least one legal guardian (EST-R03.5), exactly one primary contact
     * (EST-R03.6), each guardian once.
     *
     * @param  list<Guardianship>  $guardianships
     * @return list<BusinessRuleViolation>
     */
    private static function guardianshipViolations(array $guardianships): array
    {
        $violations = [];
        $ids = array_map(fn (Guardianship $link) => $link->guardianId->value, $guardianships);

        if (array_filter($guardianships, fn (Guardianship $link) => $link->isLegal) === []) {
            $violations[] = BusinessRuleViolation::onField('guardians', 'El estudiante necesita al menos un representante legal.');
        }

        if ($guardianships !== [] && count(array_filter($guardianships, fn (Guardianship $link) => $link->isPrimary)) !== 1) {
            $violations[] = BusinessRuleViolation::onField('guardians', 'Marca un solo representante como contacto principal.');
        }

        if (count($ids) !== count(array_unique($ids))) {
            $violations[] = BusinessRuleViolation::onField('guardians', 'Un representante aparece dos veces.');
        }

        return $violations;
    }

    /**
     * EST-R02.4 and R02.5, for a cédula escolar being assigned.
     *
     * @param  list<Guardianship>  $guardianships
     * @return list<BusinessRuleViolation>
     */
    private static function schoolIdViolations(?SchoolId $schoolId, PersonalData $personalData, array $guardianships): array
    {
        if ($schoolId === null) {
            return [];
        }

        $violations = self::yearViolations($schoolId, $personalData);

        if (! self::isFormedWithOneOf($schoolId, $guardianships)) {
            $violations[] = BusinessRuleViolation::onField('identification', 'La cédula escolar debe formarse con la cédula de uno de sus representantes.');
        }

        return $violations;
    }

    /**
     * @return list<BusinessRuleViolation>
     */
    private static function yearViolations(SchoolId $schoolId, PersonalData $personalData): array
    {
        return $schoolId->birthYearSuffix() === $personalData->birthDate->format('y')
            ? []
            : [BusinessRuleViolation::onField('identification', 'El año de la cédula escolar no coincide con la fecha de nacimiento.')];
    }

    /**
     * @param  list<Guardianship>  $guardianships
     */
    private static function isFormedWithOneOf(SchoolId $schoolId, array $guardianships): bool
    {
        foreach ($guardianships as $link) {
            if ($link->document->nationalId()?->equals($schoolId->embeddedNationalId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the new guardians leave out the one the cédula escolar was
     * formed with (EST-R03.7).
     *
     * @param  list<Guardianship>  $guardianships
     */
    private function unlinksGuardianOf(SchoolId $schoolId, array $guardianships): bool
    {
        $kept = array_map(fn (Guardianship $link) => $link->guardianId->value, $guardianships);
        $removed = array_values(array_filter($this->guardianships, fn (Guardianship $link) => ! in_array($link->guardianId->value, $kept, true)));

        return self::isFormedWithOneOf($schoolId, $removed);
    }
}
