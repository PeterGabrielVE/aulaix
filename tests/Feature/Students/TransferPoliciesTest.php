<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 ADR 0008 (EST-R07.2, R07.4, R11.3): a transfer request is seen by
 * the institution that asks and by the one that has the student active, by
 * no other, even with raw SQL.
 */
pest()->group('rls');

function activeStudentHere(string $identification = 'V11915432100', string $status = 'active'): void
{
    DB::table('students')->insert([
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => $identification, 'status' => $status,
    ]);
}

function requestTransferHere(string $identification = 'V11915432100'): int
{
    return DB::table('transfer_requests')->insertGetId([
        'destination_name' => 'Colegio Destino', 'identification' => $identification, 'status' => 'pending', 'requested_at' => now(),
    ]);
}

/**
 * @return array{origin: Institution, destination: Institution, third: Institution, request: int}
 */
function transferScene(string $studentStatus = 'active'): array
{
    $origin = tenant();
    activeStudentHere(status: $studentStatus);
    $third = tenant();
    $destination = tenant();
    $request = requestTransferHere();

    return ['origin' => $origin, 'destination' => $destination, 'third' => $third, 'request' => $request];
}

test('the destination sees its request, and nothing of the origin', function () {
    $scene = transferScene();

    expect(DB::table('transfer_requests')->pluck('id')->all())->toBe([$scene['request']])
        ->and(DB::table('transfer_requests')->value('origin_institution_id'))->toBeNull()
        ->and(DB::table('students')->count())->toBe(0);
});

test('only the institution with the student active sees the pending request', function () {
    $scene = transferScene();

    useTenant($scene['origin']);
    expect(DB::table('transfer_requests')->pluck('id')->all())->toBe([$scene['request']]);

    useTenant($scene['third']);
    expect(DB::table('transfer_requests')->count())->toBe(0);
});

test('a withdrawn student does not make the request visible', function () {
    $scene = transferScene('withdrawn');

    useTenant($scene['origin']);

    expect(DB::table('transfer_requests')->count())->toBe(0);
});

test('the origin answers the request and keeps seeing it as its own answer', function () {
    $scene = transferScene();
    useTenant($scene['origin']);

    $updated = DB::table('transfer_requests')->where('id', $scene['request'])
        ->update(['status' => 'approved', 'origin_institution_id' => $scene['origin']->id, 'answered_at' => now()]);
    DB::table('students')->update(['status' => 'withdrawn']);

    expect($updated)->toBe(1)
        ->and(DB::table('transfer_requests')->value('status'))->toBe('approved');

    useTenant($scene['destination']);
    expect(DB::table('transfer_requests')->value('origin_institution_id'))->toBe($scene['origin']->id);
});

test('another institution can neither answer nor claim a request', function () {
    $scene = transferScene();
    useTenant($scene['third']);

    expect(DB::table('transfer_requests')->where('id', $scene['request'])->update(['status' => 'approved', 'origin_institution_id' => $scene['third']->id]))->toBe(0);

    useTenant($scene['origin']);
    expect(fn () => DB::transaction(fn () => DB::table('transfer_requests')->where('id', $scene['request'])->update(['origin_institution_id' => $scene['third']->id])))
        ->toThrow(QueryException::class);
});

test('a request is created only for the open institution and never deleted', function () {
    $scene = transferScene();

    expect(fn () => DB::transaction(fn () => DB::table('transfer_requests')->insert([
        'institution_id' => $scene['origin']->id, 'destination_name' => 'X', 'identification' => 'V1', 'status' => 'pending', 'requested_at' => now(),
    ])))->toThrow(QueryException::class)
        ->and(DB::table('transfer_requests')->delete())->toBe(0)
        ->and(DB::table('transfer_requests')->count())->toBe(1);
});

test('one pending request per identification in the destination, with a known status', function () {
    transferScene();

    expect(fn () => DB::transaction(fn () => requestTransferHere()))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('transfer_requests')->update(['status' => 'lost'])))->toThrow(QueryException::class);
});
