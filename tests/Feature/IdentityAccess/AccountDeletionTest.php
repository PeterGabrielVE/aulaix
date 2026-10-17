<?php

use AulaX\IdentityAccess\Application\Account\DeletionBlocker;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Infrastructure\IdentityAccessServiceProvider;
use AulaX\IdentityAccess\Infrastructure\Notifications\DeletionPostponedNotice;
use AulaX\IdentityAccess\Infrastructure\Notifications\DeletionScheduledNotice;
use AulaX\IdentityAccess\Infrastructure\Notifications\ResetPasswordLink;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-004 IDG-R08: deleting the global account, with 30 days of grace.
 */
beforeEach(fn () => Notification::fake());

/**
 * A teacher in two institutions, logged in centrally, password just confirmed.
 *
 * @return array{0: User, 1: Institution, 2: Institution}
 */
function deletingTeacher(): array
{
    $norte = tenant();
    $teacher = userWithRole($norte, 'Docente', ['email' => 'ana@example.com']);
    $sur = tenant();
    joinInstitution($teacher, $sur, 'Docente');
    leaveTenant();
    test()->actingAs($teacher)->withSession(['auth.password_confirmed_at' => time()]);

    return [$teacher, $norte, $sur];
}

function requestDeletion(): TestResponse
{
    return test()->post(centralUrl('/cuenta/eliminar'), ['confirmation' => true]);
}

function membershipIn(Institution $institution, User $user): object
{
    useTenant($institution);
    $membership = DB::table('institution_memberships')->where('user_id', $user->id)->sole();
    leaveTenant();

    return $membership;
}

test('deleting the account asks for the password again and an explicit confirmation', function () {
    [$teacher] = deletingTeacher();

    $this->post(centralUrl('/cuenta/eliminar'))->assertSessionHasErrors('confirmation');

    $this->withSession(['auth.password_confirmed_at' => time() - 601]);
    requestDeletion()->assertRedirect(centralUrl('/confirmar-contrasena'));

    expect($teacher->fresh()->account_state)->toBe('active');
});

test('the last active administrador of an institution can not delete their account', function () {
    $institution = tenant(['name' => 'Colegio Norte']);
    $admin = userWithRole($institution, 'Administrador');
    leaveTenant();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    requestDeletion()->assertSessionHasErrors(['confirmation' => 'Eres el único Administrador activo de Colegio Norte: asigna ese rol a otra persona antes de eliminar tu cuenta.']);

    expect($admin->fresh()->account_state)->toBe('active');
});

test('a deletion request starts 30 days of grace, suspends every membership and closes every session', function () {
    config(['session.driver' => 'database']);
    $this->freezeTime();
    [$teacher, $norte, $sur] = deletingTeacher();
    DB::table('sessions')->insert(['id' => 'plantel', 'user_id' => $teacher->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => 'ana@example.com', 'token' => 'x', 'created_at' => now()]);

    requestDeletion()->assertRedirect(centralUrl('/login'));

    $this->assertGuest();
    $account = $teacher->fresh();
    expect($account->account_state)->toBe('pending_deletion')
        ->and($account->deletion_due_at)->toStartWith(now()->addDays(30)->toDateString())
        ->and(membershipIn($norte, $teacher)->suspension_reason)->toBe('account_deletion')
        ->and(membershipIn($sur, $teacher)->status)->toBe('suspended')
        ->and(DB::table('sessions')->where('user_id', $teacher->id)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and(DB::table('platform_audit_entries')->where('event_type', 'identity.account.deletion_requested')->exists())->toBeTrue();
    Notification::assertSentOnDemand(DeletionScheduledNotice::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'ana@example.com');
});

test('during the grace period a login only shows the date and the way to cancel', function () {
    [$teacher, $norte] = deletingTeacher();
    requestDeletion();

    $this->post(centralUrl('/login'), ['email' => 'ana@example.com', 'password' => 'password'])
        ->assertRedirect(centralUrl('/cuenta'));

    $this->get(centralUrl('/cuenta'))->assertInertia(fn ($page) => $page->where('account.pendingDeletion', true));
    $this->get(centralUrl('/planteles'))->assertRedirect(centralUrl('/cuenta'));
    $this->post(centralUrl("/planteles/{$norte->subdomain}"))->assertNotFound();
});

test('the password can still be recovered during the grace period', function () {
    [$teacher] = deletingTeacher();
    requestDeletion();

    $this->post(centralUrl('/forgot-password'), ['email' => 'ana@example.com']);

    Notification::assertSentOnDemandTimes(ResetPasswordLink::class, 1);
});

test('invitations can not be accepted during the grace period', function () {
    [$teacher] = deletingTeacher();
    $inviting = tenant();
    $this->actingAs(userWithRole($inviting, 'Administrador'))
        ->post(tenantUrl($inviting, '/users'), ['name' => 'Ana', 'email' => 'ana@example.com', 'roles' => ['Docente']]);
    leaveTenant();
    $this->actingAs($teacher)->withSession(['auth.password_confirmed_at' => time()]);
    requestDeletion();

    $this->actingAs($teacher->fresh())->post(centralUrl("/invitaciones/{$inviting->subdomain}/unirme"))
        ->assertSessionHasErrors('email');
});

test('cancelling reactivates only the memberships suspended for the deletion', function () {
    [$teacher, $norte, $sur] = deletingTeacher();
    useTenant($sur);
    DB::table('institution_memberships')->where('user_id', $teacher->id)->update(['status' => 'suspended', 'suspension_reason' => 'institution']);
    leaveTenant();
    $this->actingAs($teacher);
    requestDeletion()->assertSessionHasNoErrors();

    $this->actingAs($teacher->fresh())->post(centralUrl('/cuenta/eliminar/cancelar'))->assertRedirect(centralUrl('/planteles'));

    expect($teacher->fresh()->account_state)->toBe('active')
        ->and($teacher->fresh()->deletion_due_at)->toBeNull()
        ->and(membershipIn($norte, $teacher)->status)->toBe('active')
        ->and(membershipIn($sur, $teacher)->suspension_reason)->toBe('institution')
        ->and(DB::table('platform_audit_entries')->where('event_type', 'identity.account.deletion_cancelled')->exists())->toBeTrue();
});

test('an account is closed when its grace period ends, keeping every record', function () {
    [$teacher, $norte] = deletingTeacher();
    requestDeletion();
    useTenant($norte);
    DB::table('audit_entries')->insert(['occurred_at' => now(), 'event_type' => 'evaluation.grade.changed', 'subject_type' => 'grade', 'subject_id' => '1', 'actor_user_id' => $teacher->id, 'origin' => 'web']);
    leaveTenant();

    $this->travel(29)->days();
    $this->artisan('identity:close-due-accounts')->assertSuccessful();
    expect($teacher->fresh()->account_state)->toBe('pending_deletion');

    $this->travel(2)->days();
    $this->artisan('identity:close-due-accounts')->assertSuccessful();

    expect($teacher->fresh()->account_state)->toBe('closed')
        ->and(membershipIn($norte, $teacher)->status)->toBe('ended')
        ->and(DB::table('platform_audit_entries')->where('event_type', 'identity.account.closed')->exists())->toBeTrue();
    useTenant($norte);
    expect(DB::table('audit_entries')->where('actor_user_id', $teacher->id)->exists())->toBeTrue();

    $this->post(centralUrl('/login'), ['email' => 'ana@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
});

test('a closing that something still blocks is postponed, telling the owner and support', function () {
    config(['identity.support_email' => 'soporte@aulaix.test']);
    $institution = tenant(['name' => 'Colegio Norte']);
    $leaving = userWithRole($institution, 'Administrador', ['email' => 'ana@example.com']);
    $other = userWithRole($institution, 'Administrador');
    leaveTenant();
    $this->actingAs($leaving)->withSession(['auth.password_confirmed_at' => time()]);
    requestDeletion()->assertSessionHasNoErrors();
    useTenant($institution);
    DB::table('institution_memberships')->where('user_id', $other->id)->update(['status' => 'suspended', 'suspension_reason' => 'institution']);
    leaveTenant();

    $this->travel(31)->days();
    $this->artisan('identity:close-due-accounts')->assertSuccessful();

    expect($leaving->fresh()->account_state)->toBe('pending_deletion')
        ->and($leaving->fresh()->deletion_due_at)->toStartWith(now()->addDays(7)->toDateString())
        ->and(DB::table('platform_audit_entries')->where('event_type', 'identity.account.deletion_postponed')->exists())->toBeTrue();
    Notification::assertSentOnDemand(DeletionPostponedNotice::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'ana@example.com');
    Notification::assertSentOnDemand(DeletionPostponedNotice::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'soporte@aulaix.test');
});

test('due accounts are closed every day', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'identity:close-due-accounts'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 3 * * *');
});

test('other modules can block a deletion through the extension point', function () {
    // As SPEC-009 (only legal guardian) or SPEC-008 (only teacher) will.
    app()->bind('test.blocker', fn () => new class implements DeletionBlocker
    {
        public function reasonsFor(AccountId $account): array
        {
            return ['Eres el único representante legal de un estudiante.'];
        }
    });
    app()->tag(['test.blocker'], IdentityAccessServiceProvider::DELETION_BLOCKERS);
    [$teacher] = deletingTeacher();

    requestDeletion()->assertSessionHasErrors(['confirmation' => 'Eres el único representante legal de un estudiante.']);

    expect($teacher->fresh()->account_state)->toBe('active');
});
