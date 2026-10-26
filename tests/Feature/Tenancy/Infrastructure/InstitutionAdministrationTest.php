<?php

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Shared\Domain\NotFound;
use AulaX\Tenancy\Application\Administration\ListAllInstitutionsQuery;
use AulaX\Tenancy\Application\Administration\SetInstitutionStatusHandler;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;

/**
 * SPEC-014 PLT-R04.1, R04.2, R05.1, R05.4, R05.5: every institution for the
 * platform, active or not; and its status, which only the platform changes.
 */
beforeEach(function () {
    leaveTenant();
    $this->norte = InstitutionModel::factory()->create(['name' => 'Colegio Norte', 'subdomain' => 'norte', 'type' => 'privada']);
    $this->sur = InstitutionModel::factory()->create(['name' => 'Liceo Sur', 'subdomain' => 'sur']);
    $this->cerrado = InstitutionModel::factory()->inactive()->create(['name' => 'Colegio Cerrado', 'subdomain' => 'cerrado']);
});

test('lists every institution by name, with its type, status and creation date', function () {
    $page = app(ListAllInstitutionsQuery::class)(null, null, 1);

    expect(array_column($page->items, 'name'))->toBe(['Colegio Cerrado', 'Colegio Norte', 'Liceo Sur'])
        ->and($page->total)->toBe(3)
        ->and($page->items[1])->toMatchArray(['subdomain' => 'norte', 'type' => 'privada', 'status' => 'active'])
        ->and($page->items[1]['createdAt'])->toBeString();
});

test('searches by name or subdomain, and filters by status', function () {
    $query = app(ListAllInstitutionsQuery::class);

    expect(array_column($query('colegio', null, 1)->items, 'subdomain'))->toBe(['cerrado', 'norte'])
        ->and(array_column($query('SUR', null, 1)->items, 'subdomain'))->toBe(['sur'])
        ->and(array_column($query(null, 'inactive', 1)->items, 'subdomain'))->toBe(['cerrado'])
        ->and($query('100%', null, 1)->items)->toBe([]);
});

test('deactivates and reactivates an institution, touching nothing else', function () {
    $before = $this->norte->fresh()->only(['name', 'subdomain', 'dea_code', 'type']);
    $handler = app(SetInstitutionStatusHandler::class);

    expect($handler(InstitutionId::fromInt($this->norte->id), false))->toBeTrue()
        ->and($this->norte->fresh()->status->value)->toBe('inactive')
        ->and($handler(InstitutionId::fromInt($this->norte->id), false))->toBeFalse()
        ->and($handler(InstitutionId::fromInt($this->norte->id), true))->toBeTrue()
        ->and($this->norte->fresh()->status->value)->toBe('active')
        ->and($this->norte->fresh()->only(['name', 'subdomain', 'dea_code', 'type']))->toBe($before);
});

test('refuses an unknown institution', function () {
    expect(fn () => app(SetInstitutionStatusHandler::class)(InstitutionId::fromInt(999999), false))->toThrow(NotFound::class);
});
