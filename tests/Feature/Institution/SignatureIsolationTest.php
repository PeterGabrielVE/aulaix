<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-006 (S-01): the signatures belong to their institution, isolated by
 * the database. Raw SQL on purpose.
 */
pest()->group('rls');

function rawSignature(string $role = 'director'): void
{
    DB::table('institution_signatures')->insert(['role' => $role, 'signer_name' => 'Ana', 'signer_title' => 'Directora', 'file_path' => 'x.png']);
}

test('an institution only sees its own signatures', function () {
    tenant();
    rawSignature();
    $own = tenant();
    rawSignature();

    expect(DB::table('institution_signatures')->pluck('institution_id')->all())->toBe([$own->id]);
});

test('with no institution active no signature is visible', function () {
    tenant();
    rawSignature();
    RowLevelSecurity::clearInstitution();

    expect(DB::table('institution_signatures')->count())->toBe(0);
});

test('an institution has one signature per role, of a known role', function () {
    tenant();
    rawSignature('director');

    expect(fn () => DB::transaction(fn () => rawSignature('director')))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => rawSignature('rector')))->toThrow(QueryException::class);
});

test('an institution has a known type, or none yet', function () {
    $institution = tenant();

    expect(DB::table('institutions')->where('id', $institution->id)->value('type'))->toBeNull()
        ->and(DB::table('institutions')->where('id', $institution->id)->value('education_levels'))->toBe('[]')
        ->and(fn () => DB::transaction(fn () => DB::table('institutions')->where('id', $institution->id)->update(['type' => 'mixta'])))
        ->toThrow(QueryException::class);
});
