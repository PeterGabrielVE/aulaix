<?php

use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\StudentMarksRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R04, R05, R07.2 (HTTP): the literals of a section of
 * Primaria and the reports of one of Inicial, recorded by their guide.
 */
beforeEach(function () {
    foreach (marksSetting() as $name => $value) {
        $this->{$name} = $value;
    }
});

function marksUrl(int $section, string $path = ''): string
{
    $test = test();

    return tenantUrl($test->institution, "/notas/{$test->year}/secciones/{$section}".$path);
}

function saveMarkOverHttp(int $section, int $student, array $data): TestResponse
{
    $test = test();

    return $test->actingAs($test->guide)->from(marksUrl($section, '/literales'))->put(marksUrl($section, "/estudiantes/{$student}"), $data);
}

it('lists the sections a guide guides in «Notas»', function () {
    $this->actingAs($this->guide)->get(tenantUrl($this->institution, '/notas'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Grades/Index')
        ->has('list.sheets', 0)
        ->where('list.sections.0.section', '2.º nivel A')
        ->where('list.sections.0.kind', 'report')
        ->where('list.sections.1.section', '1.er grado A')
        ->where('list.sections.1.kind', 'literal')
        ->where('list.sections.1.guides', true));
});

it('records the literal of a term and shows it in the sheet of the section', function () {
    saveMarkOverHttp($this->primary, $this->ana, ['kind' => 'literal', 'period' => '1', 'literal' => 'b', 'description' => 'Lee con fluidez.'])
        ->assertRedirect(marksUrl($this->primary, '/literales'))->assertSessionHas('success', 'Se guardó el literal.');

    $this->actingAs($this->guide)->get(marksUrl($this->primary, '/literales'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Grades/Marks')
        ->where('sheet.kind', 'literal')
        ->where('sheet.section', '1.er grado A')
        ->where('sheet.canGrade', true)
        ->where('sheet.editable.1', true)
        ->where('sheet.finalOpen', false)
        ->where('sheet.rows.0.fullName', 'Pérez, Ana')
        ->where('sheet.rows.0.marks.1.literal', 'B')
        ->where('sheet.rows.0.marks.1.description', 'Lee con fluidez.'));
});

it('says what is wrong with a literal or a report', function () {
    saveMarkOverHttp($this->primary, $this->ana, ['kind' => 'literal', 'period' => '1', 'literal' => 'F'])->assertSessionHasErrors(['literal' => 'El literal debe ser A, B, C, D o E.']);
    saveMarkOverHttp($this->primary, $this->ana, ['kind' => 'literal', 'period' => 'final', 'literal' => 'A'])->assertSessionHasErrors(['period' => 'El literal final se registra con el tercer lapso cerrado.']);
    saveMarkOverHttp($this->primary, $this->ana, ['kind' => 'literal', 'period' => '2', 'literal' => 'A'])->assertSessionHasErrors(['term' => 'El lapso aún no se abre.']);
    saveMarkOverHttp($this->preschool, $this->eva, ['kind' => 'report', 'period' => '1', 'report' => ''])->assertSessionHasErrors(['report' => 'El informe debe tener entre 1 y 5000 caracteres.']);
    saveMarkOverHttp($this->primary, $this->ana, ['kind' => 'report', 'period' => '1', 'report' => 'Texto'])->assertSessionHasErrors(['period' => 'Los informes son de Educación Inicial.']);
    saveMarkOverHttp($this->primary, $this->ana, ['kind' => 'literal', 'period' => '4', 'literal' => 'A'])->assertSessionHasErrors('period');

    expect(DB::table('student_marks')->count())->toBe(0);
});

it('records the report of a student of Inicial', function () {
    saveMarkOverHttp($this->preschool, $this->eva, ['kind' => 'report', 'period' => '1', 'report' => 'Juega con sus compañeros.'])
        ->assertSessionHas('success', 'Se guardó el informe.');

    $marks = app(StudentMarksRepository::class)->forStudents($this->year, [$this->eva])[$this->eva];

    expect($marks->markOf('1', MarkKind::Report)?->description)->toBe('Juega con sus compañeros.');
});

it('records the final literal once the third term is closed', function () {
    DB::table('terms')->update(['status' => 'closed']);

    saveMarkOverHttp($this->primary, $this->ana, ['kind' => 'literal', 'period' => 'final', 'literal' => 'A'])->assertSessionHas('success', 'Se guardó el literal.');

    $this->actingAs($this->guide)->get(marksUrl($this->primary, '/literales'))->assertInertia(fn ($page) => $page
        ->where('sheet.finalOpen', true)
        ->where('sheet.rows.0.marks.final.literal', 'A'));
});

it('only the guide records them; a manager sees them; another teacher does not find them', function () {
    $other = userWithRole($this->institution, 'Docente');
    $director = userWithRole($this->institution, 'Director');

    $this->actingAs($other)->put(marksUrl($this->primary, "/estudiantes/{$this->ana}"), ['kind' => 'literal', 'period' => '1', 'literal' => 'A'])->assertForbidden();
    $this->actingAs($other)->get(marksUrl($this->primary, '/literales'))->assertNotFound();
    $this->actingAs($director)->get(marksUrl($this->primary, '/literales'))->assertOk()->assertInertia(fn ($page) => $page->where('sheet.canGrade', false));
    $this->actingAs($director)->put(marksUrl($this->primary, "/estudiantes/{$this->ana}"), ['kind' => 'literal', 'period' => '1', 'literal' => 'A'])->assertForbidden();

    expect(DB::table('student_marks')->count())->toBe(0);
});
