<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F1-02: proves the isolation is enforced by Postgres itself, not just the
 * Eloquent global scope — the scope is bypassed here on purpose.
 */
test('a tenant cannot read another tenant\'s users even without the Eloquent scope', function () {
    $institutionA = tenant();
    $userA = User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    User::factory()->for($institutionB)->create();

    // Currently "in" institution B (the last tenant() call set the RLS
    // session variable to it). Even bypassing the Eloquent global scope,
    // Postgres RLS must still hide institution A's row.
    $visibleIds = User::withoutGlobalScopes()->pluck('id');

    expect($visibleIds)->not->toContain($userA->id);
});

test('raw SQL is also subject to Row Level Security', function () {
    $institutionA = tenant();
    User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    User::factory()->for($institutionB)->create();

    $count = DB::table('users')->count();

    expect($count)->toBe(1);
});

test('a request on one subdomain cannot see users seeded for another', function () {
    $institutionA = tenant();
    $userA = User::factory()->for($institutionA)->create();

    $institutionB = tenant();
    User::factory()->for($institutionB)->create();

    $this->post(tenantUrl($institutionB, '/login'), [
        'email' => $userA->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('inserting a row for a different institution than the session is rejected', function () {
    $institutionA = tenant();
    $institutionB = tenant(); // session now points at institution B

    expect(fn () => User::factory()->for($institutionA)->create())
        ->toThrow(\Illuminate\Database\QueryException::class);
});
