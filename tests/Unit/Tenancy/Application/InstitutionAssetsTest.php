<?php

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\ChangeLogo\ChangeLogoHandler;
use AulaX\Tenancy\Application\ChangeSignature\ChangeSignatureCommand;
use AulaX\Tenancy\Application\ChangeSignature\ChangeSignatureHandler;
use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Application\RemoveLogo\RemoveLogoHandler;
use AulaX\Tenancy\Application\RemoveSignature\RemoveSignatureHandler;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\SignatureRole;
use AulaX\Tenancy\Domain\Subdomain;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Tenancy\Images;
use Tests\Fixtures\Tenancy\InMemoryInstitutionFiles;
use Tests\Fixtures\Tenancy\InMemoryInstitutionRepository;
use Tests\Fixtures\Tenancy\InMemorySignatureRepository;

/**
 * SPEC-006 INS-R04.1 – R04.5, R06.2: the logo and the signatures replace the
 * previous ones without leaving files behind, and the audit log never keeps
 * their content nor their path.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->institutions = new InMemoryInstitutionRepository(Institution::reconstitute(
        InstitutionId::fromInt(1), 'Colegio Norte', Subdomain::fromString('norte'), InstitutionStatus::Active, InstitutionProfile::empty(),
    ));
    $this->signatures = new InMemorySignatureRepository;
    $this->files = new InMemoryInstitutionFiles;
    $this->tenant = new InMemoryCurrentTenant;
    $this->tenant->set(TenantContext::of($this->institutions->byId(InstitutionId::fromInt(1))));
    $this->canManage = true;
});

/**
 * The four use cases, wired to the test's fakes.
 */
function assets(): object
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

    return new class($test, $permissions)
    {
        public function __construct(private readonly object $test, private readonly InstitutionPermissions $permissions) {}

        public function changeLogo(?string $contents = null): void
        {
            (new ChangeLogoHandler($this->test->institutions, $this->test->tenant, $this->permissions, $this->test->files, $this->test->audit, $this->test->transactions))(
                ImageUpload::fromContents($contents ?? Images::png()),
            );
        }

        public function removeLogo(): void
        {
            (new RemoveLogoHandler($this->test->institutions, $this->test->tenant, $this->permissions, $this->test->files, $this->test->audit, $this->test->transactions))();
        }

        public function changeSignature(SignatureRole $role, string $name = 'Ana Pérez', string $title = 'Directora'): void
        {
            (new ChangeSignatureHandler($this->test->signatures, $this->test->tenant, $this->permissions, $this->test->files, $this->test->audit, $this->test->transactions))(
                new ChangeSignatureCommand($role, $name, $title, ImageUpload::fromContents(Images::jpeg())),
            );
        }

        public function removeSignature(SignatureRole $role): void
        {
            (new RemoveSignatureHandler($this->test->signatures, $this->test->tenant, $this->permissions, $this->test->files, $this->test->audit, $this->test->transactions))($role);
        }
    };
}

function logoPath(): ?string
{
    return test()->institutions->byId(InstitutionId::fromInt(1))->profile->logoPath;
}

it('stores the logo and replaces the previous one, deleting its file', function () {
    assets()->changeLogo();
    $first = logoPath();
    assets()->changeLogo(Images::jpeg());

    expect(logoPath())->not->toBe($first)
        ->and($this->files->paths())->toBe([logoPath()])
        ->and($this->files->read(logoPath())->mimeType)->toBe('image/jpeg');
});

it('keeps the previous logo and drops the new file when saving fails', function () {
    assets()->changeLogo();
    $first = logoPath();
    $this->institutions->failOnSave = new RuntimeException('database down');

    expect(fn () => assets()->changeLogo(Images::jpeg()))->toThrow(RuntimeException::class);

    expect(logoPath())->toBe($first)
        ->and($this->files->paths())->toBe([$first]);
});

it('removes the logo and its file', function () {
    assets()->changeLogo();

    assets()->removeLogo();

    expect(logoPath())->toBeNull()
        ->and($this->files->paths())->toBe([]);
});

it('keeps one signature per role, with who signs and their title', function () {
    assets()->changeSignature(SignatureRole::Director, 'Ana Pérez', 'Directora');
    assets()->changeSignature(SignatureRole::Secretary, 'Eva Ruiz', 'Secretaria');
    assets()->changeSignature(SignatureRole::Director, 'Luis Mora', 'Director');

    $director = $this->signatures->of(SignatureRole::Director);
    expect($director->signerName)->toBe('Luis Mora')
        ->and($director->signerTitle)->toBe('Director')
        ->and($this->files->paths())->toEqualCanonicalizing([$director->filePath, $this->signatures->of(SignatureRole::Secretary)->filePath]);
});

it('rejects a signature without who signs, leaving no file behind', function () {
    expect(fn () => assets()->changeSignature(SignatureRole::Director, ' ', ''))->toThrow(BusinessRuleViolations::class);

    expect($this->signatures->all())->toBe([])
        ->and($this->files->paths())->toBe([]);
});

it('removes a signature and its file', function () {
    assets()->changeSignature(SignatureRole::Secretary);

    assets()->removeSignature(SignatureRole::Secretary);

    expect($this->signatures->of(SignatureRole::Secretary))->toBeNull()
        ->and($this->files->paths())->toBe([]);
});

it('records each change without the image content nor its path', function () {
    assets()->changeLogo();
    assets()->removeLogo();
    assets()->changeSignature(SignatureRole::Director, 'Ana Pérez', 'Directora');
    assets()->changeSignature(SignatureRole::Director, 'Ana Pérez', 'Directora (E)');
    assets()->removeSignature(SignatureRole::Director);

    expect($this->audit->types())->toBe([
        'tenancy.institution.logo_changed',
        'tenancy.institution.logo_removed',
        'tenancy.signature.changed',
        'tenancy.signature.changed',
        'tenancy.signature.removed',
    ])->and($this->audit->changesOf(3))->toBe(['signer_title' => ['from' => 'Directora', 'to' => 'Directora (E)']])
        ->and($this->audit->recorded[2]['event']->subject->id)->toBe('director')
        ->and(collect($this->audit->recorded)->every(fn (array $entry) => $entry['inTransaction']))->toBeTrue()
        ->and(json_encode(array_map(fn (array $entry) => $entry['event'], $this->audit->recorded)))->not->toContain('institutions/');
});

it('does nothing when there is nothing to remove', function () {
    assets()->removeLogo();
    assets()->removeSignature(SignatureRole::Director);

    expect($this->audit->types())->toBe([]);
});

it('refuses whoever may not manage the institution, storing nothing', function (string $action) {
    $this->canManage = false;

    expect(fn () => match ($action) {
        'logo' => assets()->changeLogo(),
        'remove logo' => assets()->removeLogo(),
        'signature' => assets()->changeSignature(SignatureRole::Director),
        'remove signature' => assets()->removeSignature(SignatureRole::Director),
    })->toThrow(AuthorizationDenied::class);

    expect($this->files->paths())->toBe([]);
})->with(['logo', 'remove logo', 'signature', 'remove signature']);
