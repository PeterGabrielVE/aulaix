<?php

use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 EST-R08.4, R08.5 over HTTP: a guardian asks to correct a datum,
 * the managers see it and mark it attended, the guardian sees that.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->admin = userWithRole($this->institution, 'Administrador');

    foreach ([['Ana', 'V11915432100', 'V15432100'], ['Luis', 'V11920111222', 'V20111222']] as [$name, $identification, $document]) {
        $this->actingAs($this->admin)->post(tenantUrl($this->institution, '/estudiantes'), [
            'given_names' => $name, 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
            'identification_type' => 'school_id', 'identification' => $identification,
            'guardians' => [[
                'given_names' => 'Madre', 'family_names' => $name, 'document_type' => 'national_id', 'document' => $document,
                'phone' => '0414', 'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true,
            ]],
        ])->assertSessionHasNoErrors();
    }

    $this->own = (int) DB::table('students')->where('given_names', 'Ana')->value('id');
    $this->other = (int) DB::table('students')->where('given_names', 'Luis')->value('id');
    $this->guardianUser = userWithRole($this->institution, 'Representante');
    DB::table('guardians')->where('document', 'V15432100')->update(['account_id' => $this->guardianUser->id]);
});

function askForCorrection(array $data)
{
    return test()->actingAs(test()->guardianUser)->post(tenantUrl(test()->institution, '/mis-representados/correcciones'), $data);
}

test('a guardian asks for a correction, a manager attends it, and the guardian sees it attended', function () {
    askForCorrection(['student_id' => $this->own, 'text' => 'La fecha de nacimiento es 05/03/2019'])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(tenantUrl($this->institution, '/correcciones'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Corrections/Index')
            ->where('corrections.0.text', 'La fecha de nacimiento es 05/03/2019')
            ->where('corrections.0.guardianName', 'Madre Ana')
            ->where('corrections.0.studentName', 'Pérez, Ana')
            ->where('corrections.0.status', 'pending'));

    $id = (int) DB::table('correction_requests')->value('id');
    $this->actingAs($this->admin)->post(tenantUrl($this->institution, "/correcciones/{$id}/atendida"))->assertSessionHasNoErrors();

    $this->actingAs($this->guardianUser)->get(tenantUrl($this->institution, '/mis-representados'))
        ->assertInertia(fn ($page) => $page->where('corrections.0.status', 'resolved')->where('corrections.0.text', 'La fecha de nacimiento es 05/03/2019'));
});

test('a guardian asks about its own data too', function () {
    askForCorrection(['student_id' => null, 'text' => 'Cambié de teléfono: 0424-1112233'])->assertSessionHasNoErrors();

    expect(DB::table('correction_requests')->value('student_id'))->toBeNull();
});

test('a guardian can not ask about a student it does not represent', function () {
    askForCorrection(['student_id' => $this->other, 'text' => 'Algo'])->assertNotFound();
});

test('a correction needs its text', function () {
    askForCorrection(['student_id' => $this->own, 'text' => ''])->assertSessionHasErrors('text');
});

test('only managers see and attend the corrections', function () {
    $teacher = userWithRole($this->institution, 'Docente');

    $this->actingAs($teacher)->get(tenantUrl($this->institution, '/correcciones'))->assertForbidden();
    $this->actingAs($this->guardianUser)->get(tenantUrl($this->institution, '/correcciones'))->assertForbidden();
});
