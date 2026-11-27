<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-NF05: «Grupos de pendientes» and the sheet of a group pass
 * axe on a wide screen and at 360 px, and nothing scrolls sideways. Level
 * 3, as the other screens (SPEC-013).
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $before = enrollmentYear(2025, 'closed');
    $previous = enrollmentRow($this->luis, $before, enrollmentSection($before, 'media_2'), [
        'grade' => 'media_2', 'starts_on' => '2025-09-16', 'ends_on' => '2026-07-15', 'end_reason' => 'section_change',
        'outcome' => 'promoted_with_pending', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1,
    ]);
    DB::table('enrollment_pending_subjects')->insert(['enrollment_id' => $previous, 'subject_id' => $this->math]);
    $this->group = DB::table('pending_groups')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'origin_grade' => 'media_2', 'teacher_account_id' => $this->teacher->id]);
    $plan = DB::table('evaluation_plans')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'term' => 1, 'pending_group_id' => $this->group]);
    evaluationActivityRow($plan);
});

test('the groups of pending subjects and the sheet of a group are accessible', function (string $role, string $path, int $width) {
    $this->actingAs($role === 'Docente' ? $this->teacher : userWithRole($this->institution, $role));

    visit(str_replace('{group}', (string) $this->group, $path))
        ->withHost("{$this->institution->subdomain}.localhost")
        ->resize($width, 800)
        ->assertNoAccessibilityIssues(3)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with([
    'Grupos' => ['Director', '/notas/pendientes'],
    'Planilla del grupo' => ['Docente', '/notas/pendientes/{group}/lapsos/1'],
])->with(['wide' => [1280], 'phone' => [360]]);
