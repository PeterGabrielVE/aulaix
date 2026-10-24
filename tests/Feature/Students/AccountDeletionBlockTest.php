<?php

use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 EST-R08.6 (IDG-R08.3): an account can not be deleted while it is
 * the only legal guardian of an active student.
 */
beforeEach(function () {
    $this->institution = tenant(['name' => 'Colegio Norte']);
    $admin = userWithRole($this->institution, 'Administrador');
    $this->actingAs($admin)->post(tenantUrl($this->institution, '/estudiantes'), [
        'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04', 'nationality' => 'V',
        'identification_type' => 'school_id', 'identification' => 'V11915432100',
        'guardians' => [
            ['given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id', 'document' => 'V15432100',
                'phone' => '0414', 'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true],
            ['given_names' => 'Luis', 'family_names' => 'Pérez', 'document_type' => 'national_id', 'document' => 'V20111222',
                'phone' => '0424', 'relationship' => 'father', 'is_legal' => false, 'is_primary' => false],
        ],
    ])->assertSessionHasNoErrors();

    $this->mother = userWithRole($this->institution, 'Representante', ['email' => 'maria@correo.ve']);
    $this->father = userWithRole($this->institution, 'Representante', ['email' => 'luis@correo.ve']);
    DB::table('guardians')->where('document', 'V15432100')->update(['account_id' => $this->mother->id]);
    DB::table('guardians')->where('document', 'V20111222')->update(['account_id' => $this->father->id]);
    leaveTenant();
});

function deleteAccountOf($user)
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
        ->post(centralUrl('/cuenta/eliminar'), ['confirmation' => true]);
}

test('the only legal guardian of an active student can not delete its account', function () {
    deleteAccountOf($this->mother)
        ->assertSessionHasErrors(['confirmation' => 'Eres el único representante legal de Ana Pérez en Colegio Norte: pide al plantel que registre otro representante legal antes de eliminar tu cuenta.']);

    expect($this->mother->fresh()->account_state)->toBe('active');
});

test('a guardian who is not the legal one is not blocked', function () {
    deleteAccountOf($this->father)->assertSessionHasNoErrors();
});

test('a withdrawn student no longer blocks it', function () {
    useTenant($this->institution);
    DB::table('students')->update(['status' => 'withdrawn']);
    leaveTenant();

    deleteAccountOf($this->mother)->assertSessionHasNoErrors();
});

test('another legal guardian lifts the block', function () {
    useTenant($this->institution);
    DB::table('guardianships')->where('relationship', 'father')->update(['is_legal' => true]);
    leaveTenant();

    deleteAccountOf($this->mother)->assertSessionHasNoErrors();
});
