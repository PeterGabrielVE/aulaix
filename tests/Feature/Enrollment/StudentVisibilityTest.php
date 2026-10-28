<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R08.1 – R08.4: Docente and Coordinador see the students of
 * their sections or grades, and only them; whoever manages sees everyone.
 */
beforeEach(function () {
    $this->institution = tenant(['education_levels' => ['primaria']]);
    $this->year = enrollmentYear();
    $this->third = enrollmentSection($this->year, 'primaria_3', 'A');
    $this->fourth = enrollmentSection($this->year, 'primaria_4', 'A');
    $this->fifth = enrollmentSection($this->year, 'primaria_5', 'A');
    $this->ana = enrollmentStudent('Pérez', 'Ana');
    $this->luis = enrollmentStudent('Álvarez', 'Luis', '2017-03-10');
    $this->eva = enrollmentStudent('Mora', 'Eva', '2016-03-10');
    $this->paz = enrollmentStudent('Rojas', 'Paz');
    enrollmentRow($this->ana, $this->year, $this->third);
    enrollmentRow($this->luis, $this->year, $this->fourth, ['grade' => 'primaria_4']);
    enrollmentRow($this->eva, $this->year, $this->fifth, ['grade' => 'primaria_5']);
});

function listedFor(object $user): array
{
    $test = test();

    return $test->actingAs($user)->get(tenantUrl($test->institution, '/estudiantes'))->assertOk()
        ->viewData('page')['props']['students'];
}

function namesOf(array $students): array
{
    return array_map(fn (array $student) => $student['fullName'], $students);
}

it('a teacher sees the students of the sections it teaches in', function () {
    $teacher = userWithRole($this->institution, 'Docente');
    $subject = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    DB::table('teaching_assignments')->insert(['section_id' => $this->third, 'subject_id' => $subject, 'account_id' => $teacher->id]);

    expect(namesOf(listedFor($teacher)))->toBe(['Pérez, Ana']);
});

it('the guide sees the students of its section', function () {
    $guide = userWithRole($this->institution, 'Docente');
    DB::table('sections')->where('id', $this->fourth)->update(['guide_account_id' => $guide->id]);

    expect(namesOf(listedFor($guide)))->toBe(['Álvarez, Luis']);
});

it('the coordinator sees the students of the grades it coordinates', function () {
    $coordinator = userWithRole($this->institution, 'Coordinador');
    DB::table('coordinator_assignments')->insert(['school_year_id' => $this->year, 'account_id' => $coordinator->id, 'level' => 'primaria', 'grade' => 'primaria_5']);

    expect(namesOf(listedFor($coordinator)))->toBe(['Mora, Eva']);
});

it('the record of a student of another section does not exist for it', function () {
    $guide = userWithRole($this->institution, 'Docente');
    DB::table('sections')->where('id', $this->fourth)->update(['guide_account_id' => $guide->id]);

    $this->actingAs($guide)->get(tenantUrl($this->institution, "/estudiantes/{$this->ana}"))->assertNotFound();
    $this->actingAs($guide)->get(tenantUrl($this->institution, "/estudiantes/{$this->luis}"))->assertOk();
});

it('leaving the section, it no longer sees them', function () {
    $guide = userWithRole($this->institution, 'Docente');
    DB::table('sections')->where('id', $this->fourth)->update(['guide_account_id' => $guide->id]);
    listedFor($guide);
    DB::table('sections')->where('id', $this->fourth)->update(['guide_account_id' => null]);

    expect(listedFor($guide))->toBe([]);
});

it('whoever manages students or enrollments sees everyone', function () {
    $director = userWithRole($this->institution, 'Director');

    expect(namesOf(listedFor($director)))->toBe(['Álvarez, Luis', 'Mora, Eva', 'Pérez, Ana', 'Rojas, Paz']);
});

it('the list of a section shows a teacher only the students it sees', function () {
    $guide = userWithRole($this->institution, 'Docente');
    DB::table('sections')->where('id', $this->fourth)->update(['guide_account_id' => $guide->id]);

    $this->actingAs($guide)->get(tenantUrl($this->institution, "/inscripciones/{$this->year}/secciones/{$this->third}"))->assertOk()
        ->assertInertia(fn ($page) => $page->where('roster.students', []));
    $this->actingAs($guide)->get(tenantUrl($this->institution, '/inscripciones'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('overview.unenrolled', []));
});
