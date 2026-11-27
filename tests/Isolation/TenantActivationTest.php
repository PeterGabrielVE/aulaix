<?php

use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-015 RLT-R02, ADR 0011: outside a transaction there is nothing to
 * hold an institution, so activating one is a mistake that fails loudly;
 * within() opens its own transaction and leaves nothing behind.
 */
beforeEach(function () {
    $this->norte = TenantContext::of(InstitutionMapper::toDomain(Institution::factory()->create()));
});

test('activating an institution outside any transaction is refused', function () {
    expect(fn () => app(TenantActivator::class)->activate($this->norte))->toThrow(LogicException::class)
        ->and(RowLevelSecurity::currentSetting())->toBe('');
});

test('within() outside any transaction opens its own and leaves no institution after it', function () {
    $inside = app(TenantActivator::class)->within($this->norte, fn () => RowLevelSecurity::currentSetting());

    expect($inside)->toBe((string) $this->norte->institutionId->value)
        ->and(RowLevelSecurity::currentSetting())->toBe('');
});

test('the setting disappears when its transaction ends', function () {
    DB::transaction(fn () => RowLevelSecurity::setInstitution($this->norte->institutionId->value));

    expect(RowLevelSecurity::currentSetting())->toBe('');

    try {
        DB::transaction(function () {
            RowLevelSecurity::setInstitution($this->norte->institutionId->value);

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    expect(RowLevelSecurity::currentSetting())->toBe('');
});
