<?php

use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Infrastructure\Institutions\InstitutionStatusMail;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';
require_once dirname(__DIR__, 2).'/Fixtures/Workspace/home_helpers.php';

/**
 * SPEC-014 PLT-R04, R05 and NF04 over HTTP: every operator sees the
 * institutions and their figures, never a person (V2); only a SuperAdmin,
 * with a fresh TOTP, switches one off or on, with a reason.
 */
beforeEach(function () {
    Notification::fake();
    Storage::fake(config('tenancy.files_disk'));
    $this->norte = tenant(['name' => 'Colegio Norte', 'subdomain' => 'norte']);
    userWithRole($this->norte, 'Administrador', ['name' => 'Rosa Pinto', 'email' => 'rosa@norte.ve']);
    userWithRole($this->norte, 'Docente', ['name' => 'Pedro Silva', 'email' => 'pedro@norte.ve']);
    seedStudent($this->norte->id, 'V12345678901', name: 'Lucía');
    $this->sur = tenant(['name' => 'Liceo Sur', 'subdomain' => 'sur', 'status' => 'inactive']);
    leaveTenant();
    $this->boss = signedInOperator();
});

test('lists the institutions, searched and filtered', function () {
    $this->get(adminUrl('/planteles'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Platform/Institutions')
        ->where('institutions.total', 2)
        ->where('institutions.items.0.name', 'Colegio Norte')
        ->where('institutions.items.0.subdomain', 'norte')
        ->where('institutions.items.1.status', 'inactive')
    );

    $this->get(adminUrl('/planteles?search=SUR'))->assertInertia(fn ($page) => $page->where('institutions.total', 1)->where('filters.search', 'SUR'));
    $this->get(adminUrl('/planteles?status=active'))->assertInertia(fn ($page) => $page->where('institutions.items.0.name', 'Colegio Norte')->where('institutions.total', 1));
});

test('shows the usage of an institution as figures only, and records the query (V2)', function () {
    $response = $this->get(adminUrl("/planteles/{$this->norte->id}"))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Platform/Institution')
        ->where('institution.name', 'Colegio Norte')
        ->where('usage.members.Administrador', 1)
        ->where('usage.members.Docente', 1)
        ->where('usage.activeStudents', 1)
        ->where('usage.activeYear', null)
    );

    $props = json_encode($response->viewData('page')['props']);

    expect($props)->not->toContain('Rosa')
        ->not->toContain('rosa@norte.ve')
        ->not->toContain('Pedro')
        ->not->toContain('Lucía')
        ->not->toContain('V12345678901')
        ->and(DB::table('platform_audit_entries')->where('event_type', 'platform.institution.viewed')->value('subject_id'))->toBe((string) $this->norte->id);
});

test('an unknown institution is not found', function () {
    $this->get(adminUrl('/planteles/999999'))->assertNotFound();
});

test('deactivating closes the subdomain, keeps the data and tells the Administradores', function () {
    $before = Institution::query()->find($this->norte->id)->only(['name', 'subdomain', 'dea_code', 'type']);

    $this->from(adminUrl("/planteles/{$this->norte->id}"))
        ->put(adminUrl("/planteles/{$this->norte->id}/estado"), ['active' => false, 'reason' => 'Falta de pago'])
        ->assertRedirect(adminUrl("/planteles/{$this->norte->id}"))
        ->assertSessionHasNoErrors();

    expect(Institution::query()->find($this->norte->id)->only(['name', 'subdomain', 'dea_code', 'type']))->toBe($before)
        ->and(DB::table('platform_audit_entries')->where('event_type', 'platform.institution.deactivated')->exists())->toBeTrue();
    Notification::assertSentOnDemand(InstitutionStatusMail::class, fn (InstitutionStatusMail $mail, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'rosa@norte.ve' && $mail->reason === 'Falta de pago' && ! $mail->active);
    Notification::assertSentOnDemandTimes(InstitutionStatusMail::class, 1);

    $this->get(tenantUrl($this->norte, '/login'))->assertNotFound();
});

test('a deactivation needs a reason', function () {
    $this->from(adminUrl("/planteles/{$this->norte->id}"))
        ->put(adminUrl("/planteles/{$this->norte->id}/estado"), ['active' => false, 'reason' => '  '])
        ->assertSessionHasErrors('reason');

    expect(Institution::query()->find($this->norte->id)->status->value)->toBe('active');
    Notification::assertNothingSent();
});

test('reactivating opens the subdomain again', function () {
    $this->put(adminUrl("/planteles/{$this->sur->id}/estado"), ['active' => true, 'reason' => 'Pago recibido'])->assertSessionHasNoErrors();

    expect(Institution::query()->find($this->sur->id)->status->value)->toBe('active');
    $this->get(tenantUrl($this->sur, '/login'))->assertRedirect();
});

test('Support sees the institutions but can not switch them (PLT-R05.6)', function () {
    $this->post(adminUrl('/logout'));
    signedInOperator('soporte@aulax.ve', OperatorRole::Support);

    $this->get(adminUrl("/planteles/{$this->norte->id}"))->assertOk();
    $this->put(adminUrl("/planteles/{$this->norte->id}/estado"), ['active' => false, 'reason' => 'Falta de pago'])->assertForbidden();

    expect(Institution::query()->find($this->norte->id)->status->value)->toBe('active');
});

test('switching an institution needs a fresh TOTP (PLT-NF04)', function () {
    $this->travel(11)->minutes();
    $this->get(adminUrl('/'));

    $this->from(adminUrl("/planteles/{$this->norte->id}"))
        ->put(adminUrl("/planteles/{$this->norte->id}/estado"), ['active' => false, 'reason' => 'Falta de pago'])
        ->assertRedirect(adminUrl('/confirmar-codigo'));

    expect(Institution::query()->find($this->norte->id)->status->value)->toBe('active');
});
