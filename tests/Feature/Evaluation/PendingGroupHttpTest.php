<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R03.6, R04, R05.1 (HTTP): «Grupos de pendientes», the
 * assignment of their teacher, and the sheet of a group with its plan,
 * grades and revisions.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    // Luis owes Matemática of 2.º año, from the year before.
    $before = enrollmentYear(2025, 'closed');
    $previous = enrollmentRow($this->luis, $before, enrollmentSection($before, 'media_2'), [
        'grade' => 'media_2', 'starts_on' => '2025-09-16', 'ends_on' => '2026-07-15', 'end_reason' => 'section_change',
        'outcome' => 'promoted_with_pending', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1,
    ]);
    DB::table('enrollment_pending_subjects')->insert(['enrollment_id' => $previous, 'subject_id' => $this->math]);
    $this->director = userWithRole($this->institution, 'Director');
});

function pendingUrl(string $path = ''): string
{
    return tenantUrl(test()->institution, '/notas/pendientes'.$path);
}

function assignOverHttp(?int $teacher): TestResponse
{
    $test = test();

    return $test->actingAs($test->director)->from(pendingUrl())->put(pendingUrl('/docente'), ['subject' => $test->math, 'origin_grade' => 'media_2', 'teacher' => $teacher]);
}

function groupId(): int
{
    return (int) DB::table('pending_groups')->value('id');
}

it('lists the groups of the year with their members, and assigns their teacher', function () {
    $this->actingAs($this->director)->get(pendingUrl())->assertOk()->assertInertia(fn ($page) => $page
        ->component('Grades/PendingGroups')
        ->where('groups.0.subject', 'Matemática')
        ->where('groups.0.originGrade', '2.º año')
        ->where('groups.0.teacher', null)
        ->where('groups.0.members.0.fullName', 'Álvarez, Luis')
        ->where('teachers.0.name', 'Ana Rivas'));

    assignOverHttp($this->teacher->id)->assertRedirect(pendingUrl())->assertSessionHas('success', 'Se asignó el docente.');
    assignOverHttp($this->director->id)->assertSessionHasErrors(['teacher' => 'Solo se asigna a un miembro activo con el rol Docente.']);

    expect(DB::table('pending_groups')->value('teacher_account_id'))->toBe($this->teacher->id);
});

it('the assigned teacher finds the group in Notas, plans it and grades it', function () {
    assignOverHttp($this->teacher->id);

    $this->actingAs($this->teacher)->get(tenantUrl($this->institution, '/notas'))->assertInertia(fn ($page) => $page
        ->where('list.pendingGroups.0.groupId', groupId())
        ->where('list.pendingGroups.0.subject', 'Matemática'));

    $this->actingAs($this->teacher)->from(pendingUrl('/'.groupId().'/lapsos/1'))
        ->put(pendingUrl('/'.groupId().'/lapsos/1/plan'), ['activities' => [['name' => 'Prueba', 'date' => '2026-10-05', 'weight' => '100']]])
        ->assertSessionHas('success', 'Se guardó el plan de evaluación.');

    $activity = (int) DB::table('evaluation_activities')->value('id');

    $this->actingAs($this->teacher)->from(pendingUrl('/'.groupId().'/lapsos/1'))
        ->put(pendingUrl("/actividades/{$activity}"), ['grades' => [$this->luis => '8']])
        ->assertSessionHas('success', 'Se guardaron las notas.');
    $this->actingAs($this->teacher)->from(pendingUrl('/'.groupId().'/lapsos/1'))
        ->put(pendingUrl("/actividades/{$activity}"), ['grades' => [$this->ana => '8']])
        ->assertSessionHasErrors(["grades.{$this->ana}" => 'El estudiante no está en el grupo de pendientes.']);

    $this->actingAs($this->teacher)->get(pendingUrl('/'.groupId().'/lapsos/1'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Grades/Sheet')
        ->where('sheet.section', 'Pendientes de 2.º año')
        ->where('sheet.subject', 'Matemática')
        ->where('sheet.canGrade', true)
        ->where("sheet.rows.0.marks.{$activity}", '8')
        ->where('sheet.urls.plan', pendingUrl('/'.groupId().'/lapsos/1/plan')));
});

it('records the revision of a member who failed the pending subject', function () {
    assignOverHttp($this->teacher->id);
    $plan = DB::table('evaluation_plans')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'term' => 1, 'pending_group_id' => groupId()]);

    foreach ([1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'] as $term => $date) {
        $termPlan = $term === 1 ? $plan : DB::table('evaluation_plans')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'term' => $term, 'pending_group_id' => groupId()]);
        activityGradeRow(evaluationActivityRow($termPlan, ['date' => $date]), $this->luis, ['score' => 800]);
    }

    DB::table('terms')->update(['status' => 'closed']);

    $this->actingAs($this->teacher)->from(pendingUrl('/'.groupId().'/lapsos/3'))
        ->put(pendingUrl('/'.groupId().'/revisiones'), ['revisions' => [$this->luis => '12']])
        ->assertSessionHas('success', 'Se guardaron las revisiones.');

    expect(DB::table('grade_revisions')->where('pending_group_id', groupId())->value('score'))->toBe(1200);
});
