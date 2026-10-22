<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 over HTTP (EST-R07, R11.3, NF04): a student moves between two
 * AulaX institutions, the destination learning nothing before the origin
 * answers.
 */
beforeEach(function () {
    $this->origin = tenant(['name' => 'Colegio Norte']);
    $this->originAdmin = userWithRole($this->origin, 'Administrador');
    $this->actingAs($this->originAdmin)->post(tenantUrl($this->origin, '/estudiantes'), [
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => 'V11915432100', 'allergies' => 'Penicilina',
        'guardians' => [[
            'given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id', 'document' => 'V15432100',
            'phone' => '0414', 'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true,
        ]],
    ])->assertSessionHasNoErrors();

    $this->destination = tenant(['name' => 'Colegio Sur']);
    $this->destinationAdmin = userWithRole($this->destination, 'Administrador');
});

function asAdminOf(Institution $institution, string $method, string $path, array $data = [])
{
    $test = test();
    useTenant($institution);
    $admin = $institution->is($test->origin) ? $test->originAdmin : $test->destinationAdmin;

    return $test->actingAs($admin)->{$method}(tenantUrl($institution, $path), $data);
}

function askFor(string $identification = 'V11915432100', string $type = 'school_id')
{
    return asAdminOf(test()->destination, 'post', '/traslados', ['identification_type' => $type, 'identification' => $identification]);
}

function requestId(): int
{
    useTenant(test()->destination);

    return (int) DB::table('transfer_requests')->max('id');
}

test('the destination gets the same answer whether or not the student exists', function () {
    $existing = askFor();
    $unknown = askFor('V99999999', 'national_id');

    foreach ([$existing, $unknown] as $response) {
        $response->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Enviamos la solicitud. Si el estudiante está activo en otro plantel de AulaX, ese plantel la responderá.');
    }

    asAdminOf($this->destination, 'get', '/traslados')
        ->assertInertia(fn ($page) => $page
            ->component('Transfers/Index')
            ->has('outgoing', 2)
            ->where('outgoing.0.studentName', null)
            ->where('outgoing.0.originName', null)
            ->where('outgoing.1.status', 'pending'));
});

test('the origin sees the request with the destination and its student, and approves it', function () {
    askFor();
    $id = requestId();

    asAdminOf($this->origin, 'get', '/traslados')
        ->assertInertia(fn ($page) => $page
            ->has('incoming', 1)
            ->where('incoming.0.studentName', 'Pérez, Ana')
            ->where('incoming.0.destinationName', 'Colegio Sur'));

    asAdminOf($this->origin, 'post', "/traslados/{$id}/aprobar")->assertSessionHasNoErrors();

    useTenant($this->origin);
    expect(DB::table('students')->value('status'))->toBe('withdrawn')
        ->and(DB::table('student_withdrawals')->value('reason'))->toBe('changed_school');

    useTenant($this->destination);
    expect(DB::table('students')->first())->identification->toBe('V11915432100')->status->toBe('active')->allergies->toBe('Penicilina')
        ->and(DB::table('guardians')->value('document'))->toBe('V15432100');

    asAdminOf($this->destination, 'get', '/traslados')
        ->assertInertia(fn ($page) => $page->where('outgoing.0.status', 'approved')->where('outgoing.0.originName', 'Colegio Norte'));
});

test('the origin rejects with a reason the destination sees, and the student stays', function () {
    askFor();
    $id = requestId();

    asAdminOf($this->origin, 'post', "/traslados/{$id}/rechazar", ['reason' => ''])->assertSessionHasErrors('rejection_reason');
    asAdminOf($this->origin, 'post', "/traslados/{$id}/rechazar", ['reason' => 'Falta la solvencia'])->assertSessionHasNoErrors();

    useTenant($this->origin);
    expect(DB::table('students')->value('status'))->toBe('active');

    asAdminOf($this->destination, 'get', '/traslados')
        ->assertInertia(fn ($page) => $page->where('outgoing.0.status', 'rejected')->where('outgoing.0.rejectionReason', 'Falta la solvencia'));
});

test('an unanswered request expires after 15 days and can not be approved then', function () {
    askFor();
    $id = requestId();
    $this->travel(16)->days();

    asAdminOf($this->destination, 'get', '/traslados')->assertInertia(fn ($page) => $page->where('outgoing.0.status', 'expired'));
    asAdminOf($this->origin, 'post', "/traslados/{$id}/aprobar")->assertSessionHasErrors('status');

    useTenant($this->destination);
    expect(DB::table('students')->count())->toBe(0);
});

test('only the institution with the student can answer; for any other the request does not exist', function () {
    askFor();
    $id = requestId();
    $third = tenant();
    $thirdAdmin = userWithRole($third, 'Administrador');

    $this->actingAs($thirdAdmin)->post(tenantUrl($third, "/traslados/{$id}/aprobar"))->assertNotFound();
    asAdminOf($this->destination, 'post', "/traslados/{$id}/aprobar")->assertNotFound();
});

test('who may not manage students can not ask for, see or answer transfers', function () {
    $teacher = userWithRole($this->destination, 'Docente');

    $this->actingAs($teacher)->get(tenantUrl($this->destination, '/traslados'))->assertForbidden();
    $this->actingAs($teacher)->post(tenantUrl($this->destination, '/traslados'), ['identification_type' => 'national_id', 'identification' => 'V1'])->assertForbidden();
});

test('an institution asks for at most 20 transfers an hour', function () {
    foreach (range(1, 20) as $number) {
        askFor('V'.(20000000 + $number), 'national_id')->assertSessionHasNoErrors();
    }

    askFor('V30000000', 'national_id')->assertStatus(429);
});
