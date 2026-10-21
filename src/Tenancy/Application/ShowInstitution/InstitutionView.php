<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ShowInstitution;

/**
 * The institution screen (INS-R01.1, R05.3): its details, the options of
 * the form and, for whoever manages it, its signatures.
 */
final readonly class InstitutionView
{
    /**
     * @param  array{state_id: int, municipality_id: int, parish_id: int}|null  $location
     * @param  list<string>  $levels
     * @param  list<array{code: string, name: string}>  $levelOptions
     * @param  list<array{id: int, name: string}>  $states
     * @param  list<array{id: int, name: string}>  $municipalities  Of the chosen state.
     * @param  list<array{id: int, name: string}>  $parishes  Of the chosen municipality.
     * @param  list<SignatureView>|null  $signatures  Null when not shown to this person.
     */
    public function __construct(
        public string $name,
        public string $subdomain,
        public ?string $deaCode,
        public ?string $rif,
        public ?array $location,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public ?string $type,
        public array $levels,
        public bool $hasLogo,
        public array $levelOptions,
        public array $states,
        public array $municipalities,
        public array $parishes,
        public ?array $signatures,
    ) {}
}
