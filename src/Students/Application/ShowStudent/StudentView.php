<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ShowStudent;

/**
 * A student's record (EST-R05.4).
 */
final readonly class StudentView
{
    /**
     * @param  list<string>  $previousSchoolIds
     * @param  list<GuardianLinkView>  $guardians
     * @param  list<WithdrawalView>  $withdrawals
     * @param  ?HealthView  $health  Null for whoever does not manage students (EST-R04.2).
     * @param  bool  $hasHealthInfo  The warning every viewer gets (EST-R04.3).
     */
    public function __construct(
        public int $id,
        public string $givenNames,
        public string $familyNames,
        public string $sex,
        public string $birthDate,
        public string $nationality,
        public string $identificationType,
        public string $identification,
        public array $previousSchoolIds,
        public ?string $birthCountry,
        public ?int $birthStateId,
        public ?int $parishId,
        public ?string $address,
        public ?string $emergencyPhone,
        public string $status,
        public array $guardians,
        public array $withdrawals,
        public ?HealthView $health,
        public bool $hasHealthInfo,
    ) {}

    public function withoutHealth(): self
    {
        return new self(
            $this->id, $this->givenNames, $this->familyNames, $this->sex, $this->birthDate, $this->nationality,
            $this->identificationType, $this->identification, $this->previousSchoolIds, $this->birthCountry,
            $this->birthStateId, $this->parishId, $this->address, $this->emergencyPhone, $this->status,
            $this->guardians, $this->withdrawals, null, $this->hasHealthInfo,
        );
    }
}
