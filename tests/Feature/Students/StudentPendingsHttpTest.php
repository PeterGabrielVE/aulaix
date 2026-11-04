<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R06.1, R06.2: the record of a student shows its pending
 * subjects and how they stand, to whoever may see the record.
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
});

function recordUrl(int $student): string
{
    return tenantUrl(test()->institution, "/estudiantes/{$student}");
}

it('shows the pending subjects of the student with how they stand', function () {
    $director = userWithRole($this->institution, 'Director');

    $this->actingAs($director)->get(recordUrl($this->luis))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Students/Show')
        ->where('pendings', [[
            'subjectId' => $this->math,
            'subject' => 'Matemática',
            'originGrade' => '2.º año',
            'originSchoolYear' => '2025-2026',
            'standing' => 'studying',
            'score' => null,
            'decidedIn' => null,
        ]]));

    $this->actingAs($director)->get(recordUrl($this->ana))->assertInertia(fn ($page) => $page->where('pendings', []));
});

it('shows them to whoever sees the record, and to nobody else', function () {
    $this->actingAs($this->teacher)->get(recordUrl($this->luis))->assertOk()->assertInertia(fn ($page) => $page
        ->where('pendings.0.subject', 'Matemática'));

    $this->actingAs(userWithRole($this->institution, 'Docente'))->get(recordUrl($this->luis))->assertNotFound();
});
