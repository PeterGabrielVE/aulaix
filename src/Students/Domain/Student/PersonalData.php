<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use DateTimeImmutable;

/**
 * A student's personal data (EST-R01.1 – R01.4): names, sex, birth date and
 * nationality required; birthplace, address and emergency phone optional.
 * Whether the country, state and parish exist is the use case's call, with
 * the catalogs.
 */
final readonly class PersonalData
{
    private const array MAX_LENGTHS = [
        'given_names' => 120,
        'family_names' => 120,
        'address' => 500,
        'emergency_phone' => 30,
    ];

    private const string VENEZUELA = 'VE';

    private function __construct(
        public string $givenNames,
        public string $familyNames,
        public Sex $sex,
        public DateTimeImmutable $birthDate,
        public string $nationality,
        public ?string $birthCountry,
        public ?int $birthStateId,
        public ?int $parishId,
        public ?string $address,
        public ?string $emergencyPhone,
    ) {}

    /**
     * Every broken rule is reported at once (EST-R01.2).
     *
     * @throws BusinessRuleViolations
     */
    public static function fromInput(
        ?string $givenNames,
        ?string $familyNames,
        ?string $sex,
        ?string $birthDate,
        ?string $nationality,
        ?string $birthCountry,
        ?int $birthStateId,
        ?int $parishId,
        ?string $address,
        ?string $emergencyPhone,
        DateTimeImmutable $today,
    ): self {
        $values = [
            'given_names' => self::clean($givenNames),
            'family_names' => self::clean($familyNames),
            'address' => self::clean($address),
            'emergency_phone' => self::clean($emergencyPhone),
        ];
        $country = self::clean($birthCountry) === null ? null : strtoupper(self::clean($birthCountry));
        $violations = [];

        if ($values['given_names'] === null) {
            $violations[] = BusinessRuleViolation::onField('given_names', 'Los nombres son obligatorios.');
        }

        if ($values['family_names'] === null) {
            $violations[] = BusinessRuleViolation::onField('family_names', 'Los apellidos son obligatorios.');
        }

        foreach (self::MAX_LENGTHS as $field => $max) {
            if ($values[$field] !== null && mb_strlen($values[$field]) > $max) {
                $violations[] = BusinessRuleViolation::onField($field, "Este dato admite como máximo {$max} caracteres.");
            }
        }

        $parsedSex = Sex::tryFrom((string) $sex);

        if ($parsedSex === null) {
            $violations[] = BusinessRuleViolation::onField('sex', 'Indica el sexo.');
        }

        $born = self::date($birthDate);

        if ($born === null) {
            $violations[] = BusinessRuleViolation::onField('birth_date', 'Indica la fecha de nacimiento.');
        } elseif ($born > $today) {
            $violations[] = BusinessRuleViolation::onField('birth_date', 'La fecha de nacimiento no puede ser posterior a hoy.');
        }

        if (! in_array($nationality, ['V', 'E'], true)) {
            $violations[] = BusinessRuleViolation::onField('nationality', 'Indica si es venezolano (V) o extranjero (E).');
        }

        if ($country !== null && preg_match('/^[A-Z]{2}$/D', $country) !== 1) {
            $violations[] = BusinessRuleViolation::onField('birth_country', 'Elige un país de la lista.');
        }

        if ($birthStateId !== null && $country !== self::VENEZUELA) {
            $violations[] = BusinessRuleViolation::onField('birth_state_id', 'El estado de nacimiento solo aplica a quien nació en Venezuela.');
        }

        BusinessRuleViolations::throwIfAny($violations);

        return new self(
            $values['given_names'],
            $values['family_names'],
            $parsedSex,
            $born,
            $nationality,
            $country,
            $birthStateId,
            $parishId,
            $values['address'],
            $values['emergency_phone'],
        );
    }

    /**
     * Data read back from storage, already checked when it was saved.
     */
    public static function reconstitute(
        string $givenNames,
        string $familyNames,
        Sex $sex,
        DateTimeImmutable $birthDate,
        string $nationality,
        ?string $birthCountry,
        ?int $birthStateId,
        ?int $parishId,
        ?string $address,
        ?string $emergencyPhone,
    ): self {
        return new self($givenNames, $familyNames, $sex, $birthDate, $nationality, $birthCountry, $birthStateId, $parishId, $address, $emergencyPhone);
    }

    public function fullName(): string
    {
        return $this->givenNames.' '.$this->familyNames;
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $value));

        return $date === false || $date->format('Y-m-d') !== trim((string) $value) ? null : $date;
    }
}
