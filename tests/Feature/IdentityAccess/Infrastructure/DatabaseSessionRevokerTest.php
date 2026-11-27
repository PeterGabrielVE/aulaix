<?php

use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-004 (design: «Sesión central»): an account's sessions on every domain
 * live in one table and can be ended together.
 */
beforeEach(function () {
    config(['session.driver' => 'database']);

    foreach ([['central', 7], ['plantel', 7], ['other', 8]] as [$id, $account]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $account, 'payload' => '', 'last_activity' => time()]);
    }
});

it('ends every session of the account, on every domain', function () {
    app(SessionRevoker::class)->revokeAll(AccountId::fromInt(7));

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['other']);
});

it('can keep the session serving the current request', function () {
    app('session.store')->setId(str_pad('central', 40, 'x'));
    DB::table('sessions')->where('id', 'central')->update(['id' => str_pad('central', 40, 'x')]);

    app(SessionRevoker::class)->revokeOthers(AccountId::fromInt(7));

    expect(DB::table('sessions')->orderBy('id')->pluck('id')->all())->toBe([str_pad('central', 40, 'x'), 'other']);
});
