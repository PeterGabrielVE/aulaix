<?php

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\TenantActivationHook;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Application\TenantLeak;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use AulaX\Tenancy\Infrastructure\PostgresTenantActivator;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-015 RLT-R01.2, R02.1 – R02.3 (ADR 0011): the institution is set for
 * the transaction only; within() switches for a moment, restores and checks.
 */
beforeEach(function () {
    leaveTenant();
    $this->norte = TenantContext::of(InstitutionMapper::toDomain(Institution::factory()->create()));
    $this->sur = TenantContext::of(InstitutionMapper::toDomain(Institution::factory()->create()));
});

function activeRlsSetting(): string
{
    return RowLevelSecurity::currentSetting();
}

test('within() acts in the other institution, then gives back the previous one', function () {
    app(TenantActivator::class)->activate($this->norte);

    $inside = app(TenantActivator::class)->within($this->sur, fn () => [activeRlsSetting(), app(CurrentTenant::class)->institutionId()?->value]);

    expect($inside)->toBe([(string) $this->sur->institutionId->value, $this->sur->institutionId->value])
        ->and(activeRlsSetting())->toBe((string) $this->norte->institutionId->value)
        ->and(app(CurrentTenant::class)->institutionId()?->value)->toBe($this->norte->institutionId->value);
});

test('within() gives back the previous institution, or none, also when it fails', function () {
    expect(fn () => app(TenantActivator::class)->within($this->sur, fn () => throw new RuntimeException('half way')))->toThrow(RuntimeException::class)
        ->and(activeRlsSetting())->toBe('')
        ->and(app(CurrentTenant::class)->institutionId())->toBeNull();

    app(TenantActivator::class)->activate($this->norte);

    expect(fn () => app(TenantActivator::class)->within($this->sur, fn () => throw new RuntimeException('half way')))->toThrow(RuntimeException::class)
        ->and(activeRlsSetting())->toBe((string) $this->norte->institutionId->value);
});

test('an institution left changed after within() rolls everything back and fails loudly', function () {
    $before = DB::table('institutions')->count();
    // A hook that switches behind the activator's back once it restores.
    $rogue = new class($this->norte) implements TenantActivationHook
    {
        public function __construct(private TenantContext $norte) {}

        public function onActivated(TenantContext $context): void {}

        public function onDeactivated(): void
        {
            RowLevelSecurity::setInstitution($this->norte->institutionId->value);
        }
    };
    $activator = new PostgresTenantActivator(new InMemoryCurrentTenant, [$rogue]);

    expect(fn () => $activator->within($this->sur, fn () => Institution::factory()->create()))->toThrow(TenantLeak::class)
        ->and(DB::table('institutions')->count())->toBe($before);
});
