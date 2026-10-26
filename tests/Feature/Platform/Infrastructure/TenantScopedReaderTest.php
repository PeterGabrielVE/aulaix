<?php

use AulaX\Platform\Infrastructure\TenantScopedReader;
use AulaX\Shared\Application\PlatformReadScope;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\NotFound;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Workspace/home_helpers.php';

/**
 * SPEC-014 V1, V2 (ADR 0010): the platform reads one institution at a time,
 * under that institution's RLS, read-only, and leaves no institution
 * active afterwards, whatever happens. Only inside that read do the
 * aggregate queries answer.
 */
pest()->group('rls');

beforeEach(function () {
    $this->norte = tenant();
    seedStudent($this->norte->id, 'V11111111111');
    $this->sur = tenant();
    seedStudent($this->sur->id, 'V22222222222');
    seedStudent($this->sur->id, 'V33333333333');
    leaveTenant();
    $this->reader = app(TenantScopedReader::class);
});

function rlsSetting(): string
{
    return (string) DB::scalar("select current_setting('app.current_institution_id', true)");
}

test('reads under the institution RLS, and leaves none active', function () {
    $count = $this->reader->read($this->norte->id, fn () => DB::table('students')->count());

    expect($count)->toBe(1)
        ->and(app(CurrentTenant::class)->context())->toBeNull()
        ->and(rlsSetting())->toBe('')
        ->and(DB::table('students')->count())->toBe(0);
});

test('restores the state even when the read fails', function () {
    expect(fn () => $this->reader->read($this->norte->id, fn () => throw new RuntimeException('falló')))
        ->toThrow(RuntimeException::class, 'falló')
        ->and(app(CurrentTenant::class)->context())->toBeNull()
        ->and(rlsSetting())->toBe('');
});

test('two reads in a row do not mix their institutions', function () {
    $norte = $this->reader->read($this->norte->id, fn () => DB::table('students')->pluck('identification')->all());
    $sur = $this->reader->read($this->sur->id, fn () => DB::table('students')->pluck('identification')->all());

    expect($norte)->toBe(['V11111111111'])
        ->and($sur)->toEqualCanonicalizing(['V22222222222', 'V33333333333']);
});

test('a read can not write', function () {
    expect(fn () => $this->reader->read($this->norte->id, fn () => seedStudent($this->norte->id, 'V44444444444')))
        ->toThrow(QueryException::class);

    $this->reader->read($this->norte->id, fn () => expect(DB::table('students')->count())->toBe(1));
});

test('the aggregate queries answer only inside a platform read (V2)', function () {
    expect(fn () => PlatformReadScope::ensureActive())->toThrow(AuthorizationDenied::class);

    $inside = $this->reader->read($this->norte->id, function () {
        PlatformReadScope::ensureActive();

        return PlatformReadScope::institutionId();
    });

    expect($inside)->toBe($this->norte->id)
        ->and(fn () => PlatformReadScope::ensureActive())->toThrow(AuthorizationDenied::class);
});

test('an unknown institution is refused', function () {
    expect(fn () => $this->reader->read(999999, fn () => 1))->toThrow(NotFound::class);
});
