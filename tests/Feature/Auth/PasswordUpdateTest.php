<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\PasswordChangedNotice;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * SPEC-002 IAM-R07.3, R07.4 as kept by SPEC-004 IDG-R06: the password is the
 * account's, changed from the account on the central domain.
 */
function centralOwner(): User
{
    $user = User::factory()->for(tenant())->create();
    leaveTenant();
    test()->actingAs($user);

    return $user;
}

test('password can be updated', function () {
    Notification::fake();
    $user = centralOwner();

    $response = $this
        ->from(centralUrl('/cuenta'))
        ->put(centralUrl('/cuenta/contrasena'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(centralUrl('/cuenta'));

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    Notification::assertSentOnDemand(PasswordChangedNotice::class);
});

test('correct password must be provided to update password', function () {
    $user = centralOwner();

    $response = $this
        ->from(centralUrl('/cuenta'))
        ->put(centralUrl('/cuenta/contrasena'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(centralUrl('/cuenta'));

    $this->assertTrue(Hash::check('password', $user->refresh()->password));
});

test('changing the password closes the other sessions', function () {
    config(['session.driver' => 'database']);
    Notification::fake();
    $user = centralOwner();
    DB::table('sessions')->insert(['id' => 'otra', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->put(centralUrl('/cuenta/contrasena'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'otra')->exists())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});
