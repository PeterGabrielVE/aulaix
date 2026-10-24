<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-007 CAL-R01 – R09 over HTTP: the calendar screen of an institution.
 */
// The calendar manager of the institution under test, created on first use.
beforeEach(fn () => $this->director = null);

function yearPayload(int $start = 2026): array
{
    $end = $start + 1;

    return [
        'starts_on' => "{$start}-09-16",
        'ends_on' => "{$end}-07-15",
        'terms' => [
            ['starts_on' => "{$start}-09-16", 'ends_on' => "{$start}-12-15"],
            ['starts_on' => "{$end}-01-07", 'ends_on' => "{$end}-03-31"],
            ['starts_on' => "{$end}-04-12", 'ends_on' => "{$end}-07-15"],
        ],
    ];
}

/**
 * As a calendar manager (Director) of the institution.
 */
function calendar(Institution $institution, string $method, string $path, array $data = []): TestResponse
{
    $director = test()->director ??= userWithRole($institution, 'Director');

    return test()->actingAs($director)->{$method}(tenantUrl($institution, "/calendario{$path}"), $data);
}

function createdYearId(): int
{
    return (int) DB::table('school_years')->max('id');
}

test('a manager sees the years of the institution with their terms', function () {
    $institution = tenant();
    calendar($institution, 'post', '/anios', yearPayload());

    calendar($institution, 'get', '')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Calendar/Index')
            ->where('years.0.name', '2026-2027')
            ->where('years.0.status', 'planned')
            ->has('years.0.terms', 3)
        );
});

test('a member without the permission gets 403', function (string $role) {
    $institution = tenant();
    $member = userWithRole($institution, $role);

    $this->actingAs($member)->get(tenantUrl($institution, '/calendario'))->assertForbidden();
    $this->actingAs($member)->post(tenantUrl($institution, '/calendario/anios'), yearPayload())->assertForbidden();
})->with(['Coordinador', 'Docente', 'Representante', 'Estudiante']);

test('the administrador manages the calendar too', function () {
    $institution = tenant();

    $this->actingAs(userWithRole($institution, 'Administrador'))->get(tenantUrl($institution, '/calendario'))->assertOk();
});

test('invalid dates come back on their fields', function () {
    $institution = tenant();
    $payload = yearPayload();
    $payload['terms'][1]['starts_on'] = '2026-12-10';

    calendar($institution, 'post', '/anios', $payload)
        ->assertSessionHasErrors(['terms.1.starts_on' => 'El lapso 2 debe empezar después de que termine el lapso 1.']);
    calendar($institution, 'post', '/anios', [...yearPayload(), 'ends_on' => 'mañana'])->assertSessionHasErrors('ends_on');
});

test('the dates of a year can be corrected, and its name follows them', function () {
    $institution = tenant();
    calendar($institution, 'post', '/anios', yearPayload(2026));

    calendar($institution, 'put', '/anios/'.createdYearId(), yearPayload(2027))->assertSessionHasNoErrors();

    calendar($institution, 'get', '')->assertInertia(fn ($page) => $page->where('years.0.name', '2027-2028'));
});

test('closing asks for an explicit confirmation', function () {
    $institution = tenant();
    calendar($institution, 'post', '/anios', yearPayload());
    $id = createdYearId();
    calendar($institution, 'post', "/anios/{$id}/activar");

    calendar($institution, 'post', "/anios/{$id}/lapsos/cerrar")->assertSessionHasErrors('confirmation');
    calendar($institution, 'post', "/anios/{$id}/cerrar")->assertSessionHasErrors('confirmation');
});

test('a whole school year, from planning to closing', function () {
    $institution = tenant();
    calendar($institution, 'post', '/anios', yearPayload())->assertSessionHasNoErrors();
    $id = createdYearId();

    calendar($institution, 'post', "/anios/{$id}/activar")->assertSessionHasNoErrors();
    foreach (range(1, 3) as $term) {
        calendar($institution, 'post', "/anios/{$id}/lapsos/cerrar", ['confirmation' => true])->assertSessionHasNoErrors();
    }
    calendar($institution, 'post', "/anios/{$id}/lapsos/3/reabrir", ['reason' => 'Faltó cargar una nota'])->assertSessionHasNoErrors();
    calendar($institution, 'post', "/anios/{$id}/lapsos/cerrar", ['confirmation' => true])->assertSessionHasNoErrors();
    calendar($institution, 'post', "/anios/{$id}/cerrar", ['confirmation' => true])->assertSessionHasNoErrors();

    calendar($institution, 'get', '')->assertInertia(fn ($page) => $page
        ->where('years.0.status', 'closed')
        ->where('years.0.terms.2.status', 'closed')
    );
    expect(DB::table('audit_entries')->where('event_type', 'like', 'calendar.%')->pluck('event_type')->all())->toBe([
        'calendar.school_year.created', 'calendar.school_year.activated',
        'calendar.term.closed', 'calendar.term.closed', 'calendar.term.closed',
        'calendar.term.reopened', 'calendar.term.closed', 'calendar.school_year.closed',
    ]);
});

test('a planned year can be deleted, an active one can not', function () {
    $institution = tenant();
    calendar($institution, 'post', '/anios', yearPayload());
    $id = createdYearId();

    calendar($institution, 'delete', "/anios/{$id}", ['confirmation' => true])->assertSessionHasNoErrors();
    expect(DB::table('school_years')->count())->toBe(0);

    calendar($institution, 'post', '/anios', yearPayload());
    $active = createdYearId();
    calendar($institution, 'post', "/anios/{$active}/activar");
    calendar($institution, 'delete', "/anios/{$active}", ['confirmation' => true])->assertSessionHasErrors('status');
});

test('a year of another institution answers as not found', function () {
    $other = tenant();
    calendar($other, 'post', '/anios', yearPayload());
    $foreign = createdYearId();
    test()->director = null;

    $institution = tenant();

    calendar($institution, 'post', "/anios/{$foreign}/activar")->assertNotFound();
    calendar($institution, 'put', "/anios/{$foreign}", yearPayload())->assertNotFound();
});

test('each institution has its own active year', function () {
    $norte = tenant();
    calendar($norte, 'post', '/anios', yearPayload());
    calendar($norte, 'post', '/anios/'.createdYearId().'/activar')->assertSessionHasNoErrors();
    test()->director = null;

    $sur = tenant();
    calendar($sur, 'post', '/anios', yearPayload());
    calendar($sur, 'post', '/anios/'.createdYearId().'/activar')->assertSessionHasNoErrors();

    expect(DB::table('school_years')->where('status', 'active')->count())->toBe(1);
});

test('the menu offers the calendar only to its managers', function (string $role, bool $sees) {
    $institution = tenant();

    $this->actingAs(userWithRole($institution, $role))
        ->get(tenantUrl($institution, '/profile'))
        ->assertInertia(fn ($page) => $page->where('auth.can.manageCalendar', $sees));
})->with([
    'Director' => ['Director', true],
    'Docente' => ['Docente', false],
]);

test('terms do not open or close as their dates pass', function () {
    $institution = tenant();
    calendar($institution, 'post', '/anios', yearPayload(2026));
    calendar($institution, 'post', '/anios/'.createdYearId().'/activar');

    // Well past the end of the first term, and of the year.
    $this->travelTo(new DateTimeImmutable('2027-08-01'));

    calendar($institution, 'get', '')->assertInertia(fn ($page) => $page
        ->where('years.0.status', 'active')
        ->where('years.0.terms.0.status', 'open')
        ->where('years.0.terms.1.status', 'pending')
    );
});
