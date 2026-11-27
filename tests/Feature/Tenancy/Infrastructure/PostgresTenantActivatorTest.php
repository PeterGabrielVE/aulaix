<?php

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\TenantActivationHook;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use AulaX\Tenancy\Infrastructure\PostgresTenantActivator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

function contextOf(InstitutionModel $institution): TenantContext
{
    return new TenantContext(InstitutionId::fromInt($institution->id), $institution->name, $institution->subdomain);
}

function rlsInstitution(): ?string
{
    return DB::selectOne('select current_setting(?, true) as value', [RowLevelSecurity::SESSION_VARIABLE])->value;
}

it('points the current tenant, the database session and the permission team at the institution', function () {
    $institution = InstitutionModel::factory()->create();

    app(TenantActivator::class)->activate(contextOf($institution));

    expect(app(CurrentTenant::class)->institutionId()->value)->toBe($institution->id)
        ->and(rlsInstitution())->toBe((string) $institution->id)
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($institution->id);
});

it('leaves no tenant anywhere once deactivated', function () {
    app(TenantActivator::class)->activate(contextOf(InstitutionModel::factory()->create()));

    app(TenantActivator::class)->deactivate();

    expect(app(CurrentTenant::class)->context())->toBeNull()
        ->and(rlsInstitution())->toBe('')
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

it('tells every hook when a tenant is activated and deactivated', function () {
    $hook = new class implements TenantActivationHook
    {
        /** @var list<string> */
        public array $calls = [];

        public function onActivated(TenantContext $context): void
        {
            $this->calls[] = "activated {$context->subdomain}";
        }

        public function onDeactivated(): void
        {
            $this->calls[] = 'deactivated';
        }
    };
    $activator = new PostgresTenantActivator(new InMemoryCurrentTenant, [$hook]);

    $activator->activate(contextOf(InstitutionModel::factory()->create(['subdomain' => 'norte'])));
    $activator->deactivate();

    expect($hook->calls)->toBe(['activated norte', 'deactivated']);
});
