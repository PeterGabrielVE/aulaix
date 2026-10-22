<?php

use Illuminate\Support\Facades\DB;

/**
 * SPEC-008 EAC-R09.1 (IDG-R08.4): an account can not be deleted while it
 * teaches or guides in the active school year of an institution.
 */
beforeEach(function () {
    $this->institution = tenant(['name' => 'Colegio Norte', 'education_levels' => ['primaria']]);
    $this->teacher = userWithRole($this->institution, 'Docente');
    $this->active = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'active']);
    $this->math = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    $this->section = DB::table('sections')->insertGetId(['school_year_id' => $this->active, 'grade' => 'primaria_1', 'letter' => 'A', 'capacity' => 30]);
});

function deleteTeacherAccount()
{
    leaveTenant();

    return test()->actingAs(test()->teacher)->withSession(['auth.password_confirmed_at' => time()])
        ->post(centralUrl('/cuenta/eliminar'), ['confirmation' => true]);
}

test('whoever teaches a subject in the active year can not delete the account', function () {
    DB::table('teaching_assignments')->insert(['section_id' => $this->section, 'subject_id' => $this->math, 'account_id' => $this->teacher->id]);

    deleteTeacherAccount()->assertSessionHasErrors([
        'confirmation' => 'Tienes clases o una sección a tu cargo en el año escolar activo de Colegio Norte: pide al plantel que te reemplace antes de eliminar tu cuenta.',
    ]);
});

test('neither can the guide of a section in the active year', function () {
    DB::table('sections')->where('id', $this->section)->update(['guide_account_id' => $this->teacher->id]);

    deleteTeacherAccount()->assertSessionHasErrors('confirmation');
});

test('assignments of a closed or planned year do not block', function () {
    DB::table('teaching_assignments')->insert(['section_id' => $this->section, 'subject_id' => $this->math, 'account_id' => $this->teacher->id]);
    DB::table('school_years')->where('id', $this->active)->update(['status' => 'closed']);

    deleteTeacherAccount()->assertSessionHasNoErrors();
});
