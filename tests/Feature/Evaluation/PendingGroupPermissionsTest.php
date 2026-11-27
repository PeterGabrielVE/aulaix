<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R03.4, R04.5, R04.6, NF01: only a manager of the structure
 * assigns the teachers; only the assigned teacher plans and grades a group;
 * a coordinator of the grade of origin and the managers see it; anyone
 * else, not even that it exists.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->group = DB::table('pending_groups')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'origin_grade' => 'media_2', 'teacher_account_id' => $this->teacher->id]);
    $this->plan = DB::table('evaluation_plans')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'term' => 1, 'pending_group_id' => $this->group]);
    $this->activity = evaluationActivityRow($this->plan);
});

function groupUrl(string $path): string
{
    return tenantUrl(test()->institution, '/notas/pendientes'.$path);
}

it('a teacher does not assign itself a group, nor sees the groups', function () {
    $other = userWithRole($this->institution, 'Docente');

    $this->actingAs($other)->put(groupUrl('/docente'), ['subject' => $this->math, 'origin_grade' => 'media_2', 'teacher' => $other->id])->assertForbidden();
    $this->actingAs($other)->get(groupUrl(''))->assertForbidden();
});

it('another teacher does not see the group, and can not plan nor grade it', function () {
    $other = userWithRole($this->institution, 'Docente');

    $this->actingAs($other)->get(groupUrl("/{$this->group}/lapsos/1"))->assertNotFound();
    $this->actingAs($other)->put(groupUrl("/{$this->group}/lapsos/1/plan"), ['activities' => [['name' => 'X', 'date' => '2026-10-05', 'weight' => '100']]])->assertForbidden();
    $this->actingAs($other)->put(groupUrl("/actividades/{$this->activity}"), ['grades' => [$this->luis => '20']])->assertForbidden();

    expect(DB::table('activity_grades')->count())->toBe(0);
});

it('a coordinator of the grade of origin and a manager see the group, and grade none of it', function () {
    $coordinator = userWithRole($this->institution, 'Coordinador');
    DB::table('coordinator_assignments')->insert(['school_year_id' => $this->year, 'account_id' => $coordinator->id, 'level' => 'media_general', 'grade' => 'media_2']);
    $director = userWithRole($this->institution, 'Director');

    $this->actingAs($coordinator)->get(groupUrl("/{$this->group}/lapsos/1"))->assertOk()->assertInertia(fn ($page) => $page->where('sheet.canGrade', false));
    $this->actingAs($director)->get(groupUrl("/{$this->group}/lapsos/1"))->assertOk();
    $this->actingAs($coordinator)->put(groupUrl("/actividades/{$this->activity}"), ['grades' => [$this->luis => '20']])->assertForbidden();
});

it('the grades of a section do not take an activity of a group', function () {
    $this->actingAs($this->teacher)->put(tenantUrl($this->institution, "/notas/actividades/{$this->activity}"), ['grades' => [$this->luis => '20']])->assertNotFound();
});
