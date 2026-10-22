<?php

declare(strict_types=1);

namespace AulaX\Students\Application\StudentForm;

use AulaX\Catalogs\Application\Countries;
use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;
use DateTimeImmutable;

/**
 * Checks the student form shared by registering and editing, every broken
 * rule at once (EST-R01.2), and saves its new guardians. A new guardian with
 * the document of one the institution already has is that one, its data
 * untouched (EST-R03.3). Runs inside the use case's transaction.
 */
final readonly class StudentForm
{
    public function __construct(
        private GuardianRepository $guardians,
        private StudentRepository $students,
        private Countries $countries,
        private GeographyDirectory $geography,
        private Clock $clock,
    ) {}

    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->clock->now()->format('Y-m-d'));
    }

    /**
     * @param  list<BusinessRuleViolation>  $alsoBroken  Rules the caller checked, reported together.
     *
     * @throws BusinessRuleViolations
     */
    public function prepare(StudentFields $fields, ?StudentId $except, array $alsoBroken = []): PreparedStudent
    {
        $violations = $alsoBroken;
        $personalData = $this->attempt(fn () => PersonalData::fromInput(
            $fields->givenNames,
            $fields->familyNames,
            $fields->sex,
            $fields->birthDate,
            $fields->nationality,
            $fields->birthCountry,
            $fields->birthStateId,
            $fields->parishId,
            $fields->address,
            $fields->emergencyPhone,
            $this->today(),
        ), $violations);
        $identification = $this->attempt(fn () => StudentIdentification::fromInput((string) $fields->identificationType, (string) $fields->identification), $violations);

        $violations = [...$violations, ...$this->catalogViolations($fields)];

        if ($identification !== null && $this->students->isIdentificationTaken($identification, $except)) {
            $violations[] = BusinessRuleViolation::onField('identification', 'Ya hay un estudiante con esa identificación en el plantel. Si es un hermano nacido el mismo año, usa otro tipo de parto.');
        }

        $guardians = [];

        foreach ($fields->guardians as $index => $guardian) {
            $guardians[] = $this->guardian($guardian, "guardians.{$index}.", $violations);
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new PreparedStudent($personalData, $identification, array_map(fn (array $guardian) => $this->link(...$guardian), $guardians));
    }

    /**
     * @return list<BusinessRuleViolation>
     */
    private function catalogViolations(StudentFields $fields): array
    {
        $violations = [];
        $country = strtoupper(trim((string) $fields->birthCountry));

        if (preg_match('/^[A-Z]{2}$/D', $country) === 1 && ! $this->countries->exists($country)) {
            $violations[] = BusinessRuleViolation::onField('birth_country', 'Elige un país de la lista.');
        }

        if ($fields->birthStateId !== null && $country === 'VE' && ! $this->geography->stateExists($fields->birthStateId)) {
            $violations[] = BusinessRuleViolation::onField('birth_state_id', 'Elige un estado de la lista.');
        }

        if ($fields->parishId !== null && ! $this->geography->parishExists($fields->parishId)) {
            $violations[] = BusinessRuleViolation::onField('parish_id', 'Elige una parroquia de la lista.');
        }

        return $violations;
    }

    /**
     * The guardian of one row of the form, checked, and how it is linked.
     *
     * @param  list<BusinessRuleViolation>  $violations
     * @return array{0: Guardian|GuardianData|null, 1: ?Relationship, 2: GuardianFields}
     */
    private function guardian(GuardianFields $fields, string $prefix, array &$violations): array
    {
        $relationship = $this->attempt(fn () => Relationship::fromInput($fields->relationship, $prefix.'relationship'), $violations);

        if ($fields->guardianId !== null) {
            $existing = $fields->guardianId > 0 ? $this->guardians->byId(GuardianId::fromInt($fields->guardianId)) : null;

            if ($existing === null) {
                $violations[] = BusinessRuleViolation::onField($prefix.'guardian_id', 'Ese representante no existe.');
            }

            return [$existing, $relationship, $fields];
        }

        $nested = [];
        $data = $this->attempt(fn () => GuardianData::fromInput(
            $fields->givenNames,
            $fields->familyNames,
            $fields->documentType,
            $fields->document,
            $fields->phone,
            $fields->email,
            $fields->address,
        ), $nested);

        foreach ($nested as $violation) {
            $violations[] = BusinessRuleViolation::onField($prefix.$violation->field(), $violation->getMessage());
        }

        return [$data === null ? null : ($this->guardians->byDocument($data->document) ?? $data), $relationship, $fields];
    }

    /**
     * Saves a new guardian, then links it.
     */
    private function link(Guardian|GuardianData $guardian, Relationship $relationship, GuardianFields $fields): Guardianship
    {
        if ($guardian instanceof GuardianData) {
            $guardian = Guardian::register($guardian);
            $guardian->identify($this->guardians->save($guardian));
        }

        return new Guardianship($guardian->id(), $guardian->data()->document, $relationship, $fields->isLegal, $fields->isPrimary);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $build
     * @param  list<BusinessRuleViolation>  $violations
     * @return T|null
     */
    private function attempt(callable $build, array &$violations): mixed
    {
        try {
            return $build();
        } catch (BusinessRuleViolations $broken) {
            array_push($violations, ...$broken->violations());
        } catch (BusinessRuleViolation $broken) {
            $violations[] = $broken;
        }

        return null;
    }
}
