<?php

use AulaX\Catalogs\Application\EducationLevels;
use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Application\UpdateInstitution\UpdateInstitutionCommand;
use AulaX\Tenancy\Application\UpdateInstitution\UpdateInstitutionHandler;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\InstitutionType;
use AulaX\Tenancy\Domain\Subdomain;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Tenancy\InMemoryInstitutionRepository;

/**
 * SPEC-006 INS-R01 – R03, R06.1, R07.1: an institution's manager keeps its
 * details, type and levels; only the open institution changes, and the
 * audit log names what changed.
 */
function anInstitution(int $id, string $subdomain, ?string $deaCode = null, ?string $rif = null): Institution
{
    return Institution::reconstitute(
        InstitutionId::fromInt($id),
        "Plantel {$id}",
        Subdomain::fromString($subdomain),
        InstitutionStatus::Active,
        new InstitutionProfile(deaCode: $deaCode, rif: $rif),
    );
}

beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->institutions = new InMemoryInstitutionRepository(
        anInstitution(1, 'norte', deaCode: 'OD-1', rif: 'J-1'),
        anInstitution(2, 'sur', deaCode: 'OD-2', rif: 'J-2'),
    );
    $this->tenant = new InMemoryCurrentTenant;
    $this->tenant->set(TenantContext::of($this->institutions->byId(InstitutionId::fromInt(1))));
    $this->canManage = true;
});

function updateInstitution(array $data = []): void
{
    $test = test();
    $permissions = new class($test) implements InstitutionPermissions
    {
        public function __construct(private readonly object $test) {}

        public function canManage(): bool
        {
            return $this->test->canManage;
        }
    };
    $geography = new class implements GeographyDirectory
    {
        public function states(): array
        {
            return [];
        }

        public function municipalitiesOf(int $stateId): array
        {
            return [];
        }

        public function parishesOf(int $municipalityId): array
        {
            return [];
        }

        public function parishExists(int $parishId): bool
        {
            return $parishId === 10;
        }

        public function locationOf(int $parishId): ?array
        {
            return null;
        }
    };

    $data += [
        'name' => 'Colegio Norte', 'deaCode' => 'OD-1', 'rif' => 'J-1', 'parishId' => 10, 'address' => 'Av. Principal',
        'phone' => '0212-5550000', 'email' => 'info@norte.ve', 'type' => 'privada', 'levels' => ['primaria'],
    ];

    (new UpdateInstitutionHandler($test->institutions, $test->tenant, $permissions, new EducationLevels, $geography, $test->audit, $test->transactions))(
        new UpdateInstitutionCommand(...$data),
    );
}

function openInstitution(): Institution
{
    return test()->institutions->byId(InstitutionId::fromInt(1));
}

/**
 * @return array<string, list<string>>
 */
function institutionErrors(array $data): array
{
    try {
        updateInstitution($data);
    } catch (BusinessRuleViolations $violations) {
        return $violations->messagesByField();
    }

    test()->fail('The change was accepted.');
}

it('updates the details, type and levels of the open institution only', function () {
    updateInstitution(['levels' => ['inicial', 'primaria']]);

    expect(openInstitution()->name)->toBe('Colegio Norte')
        ->and(openInstitution()->profile->email)->toBe('info@norte.ve')
        ->and(openInstitution()->profile->parishId)->toBe(10)
        ->and(openInstitution()->type)->toBe(InstitutionType::Privada)
        ->and(openInstitution()->levels->codes())->toBe(['inicial', 'primaria'])
        ->and($this->institutions->byId(InstitutionId::fromInt(2))->name)->toBe('Plantel 2');
});

it('lets the details be left empty', function () {
    updateInstitution(['deaCode' => ' ', 'rif' => null, 'parishId' => null, 'address' => '', 'phone' => null, 'email' => null]);

    expect(openInstitution()->profile)->toEqual(InstitutionProfile::empty());
});

it('accepts a DEA code and a RIF in any format, up to their length', function () {
    updateInstitution(['deaCode' => 'cualquier cosa 1234', 'rif' => '123456789012']);

    expect(openInstitution()->profile->deaCode)->toBe('cualquier cosa 1234')
        ->and(openInstitution()->profile->rif)->toBe('123456789012');
});

it('rejects a DEA code or a RIF another institution uses, without naming it', function () {
    $errors = institutionErrors(['deaCode' => 'OD-2', 'rif' => 'J-2']);

    expect($errors)->toBe([
        'dea_code' => ['Ese código DEA ya está registrado.'],
        'rif' => ['Ese RIF ya está registrado.'],
    ])->and(openInstitution()->profile->deaCode)->toBe('OD-1');
});

it('reports every broken rule of the form at once', function () {
    $errors = institutionErrors([
        'name' => ' ', 'email' => 'no-es-correo', 'parishId' => 99, 'type' => 'militar', 'levels' => [],
        'deaCode' => str_repeat('D', 21),
    ]);

    expect(array_keys($errors))->toEqualCanonicalizing(['name', 'email', 'parish_id', 'type', 'levels', 'dea_code'])
        ->and($errors['parish_id'])->toBe(['Elige una parroquia del catálogo.']);
});

it('rejects a level that is not in the catalog', function () {
    expect(institutionErrors(['levels' => ['primaria', 'universitaria']]))
        ->toBe(['levels' => ['Elige niveles del catálogo.']]);
});

it('records only the fields that changed, inside the transaction', function () {
    updateInstitution(['email' => 'info@norte.ve']);
    updateInstitution(['email' => 'otro@norte.ve']);

    expect($this->audit->types())->toBe(['tenancy.institution.updated', 'tenancy.institution.updated'])
        ->and($this->audit->recorded[1]['inTransaction'])->toBeTrue()
        ->and($this->audit->changesOf(1))->toBe(['email' => ['from' => 'info@norte.ve', 'to' => 'otro@norte.ve']])
        ->and($this->audit->changesOf(0))->toHaveKeys(['name', 'parish_id', 'type', 'levels']);
});

it('records nothing when nothing changed', function () {
    updateInstitution();
    updateInstitution();

    expect($this->audit->types())->toHaveCount(1);
});

it('refuses whoever may not manage the institution', function () {
    $this->canManage = false;

    expect(fn () => updateInstitution())->toThrow(AuthorizationDenied::class);
    expect(openInstitution()->name)->toBe('Plantel 1');
});
