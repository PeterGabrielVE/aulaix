<?php

use AulaX\Platform\Infrastructure\Institutions\InstitutionStatusMail;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';
require_once dirname(__DIR__, 2).'/Fixtures/Workspace/home_helpers.php';

/**
 * SPEC-014 PLT-NF01 and V1, against the real RLS: the usage of one
 * institution counts only its own rows, and the panel never leaves an
 * institution active once a request is over.
 */
pest()->group('rls');

beforeEach(function () {
    Notification::fake();
    Storage::fake(config('tenancy.files_disk'));
    $this->norte = tenant();
    userWithRole($this->norte, 'Administrador', ['email' => 'rosa@norte.ve']);
    seedStudent($this->norte->id, 'V10000000001');
    $this->sur = tenant();
    userWithRole($this->sur, 'Administrador', ['email' => 'luis@sur.ve']);
    userWithRole($this->sur, 'Docente');
    userWithRole($this->sur, 'Docente');
    seedStudent($this->sur->id, 'V20000000001');
    seedStudent($this->sur->id, 'V20000000002');
    seedStudent($this->sur->id, 'V20000000003');
    leaveTenant();
    signedInOperator();
});

function expectNoInstitutionActive(): void
{
    expect((string) DB::scalar("select current_setting('app.current_institution_id', true)"))->toBe('')
        ->and(app(CurrentTenant::class)->institutionId())->toBeNull();
}

test('the usage of one institution counts only its own rows', function () {
    test()->get(adminUrl("/planteles/{$this->norte->id}"))->assertInertia(fn ($page) => $page
        ->where('usage.activeStudents', 1)
        ->where('usage.members.Administrador', 1)
        ->where('usage.members.Docente', 0)
    );

    test()->get(adminUrl("/planteles/{$this->sur->id}"))->assertInertia(fn ($page) => $page
        ->where('usage.activeStudents', 3)
        ->where('usage.members.Docente', 2)
    );
});

test('no institution is left active after reading its usage', function () {
    test()->get(adminUrl("/planteles/{$this->norte->id}"))->assertOk();

    expectNoInstitutionActive();
    expect(DB::table('platform_operators')->count())->toBe(1);
});

test('no institution is left active after switching one, and only its Administradores are told', function () {
    test()->put(adminUrl("/planteles/{$this->sur->id}/estado"), ['active' => false, 'reason' => 'Fin de contrato'])->assertSessionHasNoErrors();

    expectNoInstitutionActive();
    Notification::assertSentOnDemandTimes(InstitutionStatusMail::class, 1);
    Notification::assertSentOnDemand(
        InstitutionStatusMail::class,
        fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'luis@sur.ve',
    );
});
