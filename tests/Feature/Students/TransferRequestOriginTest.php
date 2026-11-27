<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-015 RLT-R05 (ADR 0008): nobody can present an institution as the
 * origin of a request it did not answer, even with raw SQL; the writes of
 * SPEC-009 keep working.
 */
pest()->group('rls');

beforeEach(function () {
    $this->origin = tenant();
    DB::table('students')->insert([
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => 'V11915432100', 'status' => 'active',
    ]);
    $this->third = tenant();
    $this->destination = tenant();
    $this->request = DB::table('transfer_requests')->insertGetId([
        'destination_name' => 'Colegio Destino', 'identification' => 'V11915432100', 'status' => 'pending', 'requested_at' => now(),
    ]);
});

/**
 * Runs a write in its own savepoint, so a refused one leaves the test's
 * transaction usable.
 */
function refusedWrite(callable $write): bool
{
    try {
        DB::transaction($write);
    } catch (QueryException) {
        return true;
    }

    return false;
}

test('the destination can not set an origin when it creates a request (RLT-R05.1)', function () {
    expect(refusedWrite(fn () => DB::table('transfer_requests')->insert([
        'destination_name' => 'Colegio Destino', 'identification' => 'V22222222222', 'status' => 'pending', 'requested_at' => now(),
        'origin_institution_id' => $this->third->id,
    ])))->toBeTrue()
        ->and(refusedWrite(fn () => DB::table('transfer_requests')->insert([
            'destination_name' => 'Colegio Destino', 'identification' => 'V33333333333', 'status' => 'approved', 'requested_at' => now(),
        ])))->toBeTrue();
});

test('the destination can not set or change the origin of its request (RLT-R05.1)', function () {
    expect(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update(['origin_institution_id' => $this->third->id])))->toBeTrue()
        ->and(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update(['origin_institution_id' => $this->destination->id])))->toBeTrue()
        ->and(DB::table('transfer_requests')->value('origin_institution_id'))->toBeNull();
});

test('the origin only puts itself as origin when it answers (RLT-R05.2)', function () {
    useTenant($this->origin);

    expect(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update(['status' => 'approved', 'origin_institution_id' => $this->third->id])))->toBeTrue()
        ->and(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update([
            'status' => 'approved', 'origin_institution_id' => $this->origin->id, 'origin_name' => 'Colegio Origen', 'answered_at' => now(),
        ])))->toBeFalse()
        ->and(DB::table('transfer_requests')->value('origin_institution_id'))->toBe($this->origin->id);
});

test('nobody changes the origin of an answered request, nor what it was asked for (RLT-R05.3)', function () {
    useTenant($this->origin);
    DB::table('transfer_requests')->where('id', $this->request)->update(['status' => 'rejected', 'origin_institution_id' => $this->origin->id, 'answered_at' => now()]);

    useTenant($this->destination);

    expect(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update(['origin_institution_id' => null])))->toBeTrue()
        ->and(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update(['identification' => 'V99999999999'])))->toBeTrue();

    useTenant($this->origin);

    expect(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update(['origin_institution_id' => $this->third->id])))->toBeTrue()
        ->and(DB::table('transfer_requests')->value('origin_institution_id'))->toBe($this->origin->id);
});

test('the writes of SPEC-009 keep working: the destination expires its request untouched (RLT-R05.4)', function () {
    expect(refusedWrite(fn () => DB::table('transfer_requests')->where('id', $this->request)->update([
        'destination_name' => 'Colegio Destino', 'identification' => 'V11915432100', 'status' => 'expired', 'origin_institution_id' => null,
    ])))->toBeFalse()
        ->and(DB::table('transfer_requests')->value('status'))->toBe('expired');
});
