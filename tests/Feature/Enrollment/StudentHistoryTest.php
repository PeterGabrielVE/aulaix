<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R04.6, R14.6: the record of a student shows the history of
 * its enrollments, annulments included.
 */
it('shows where the student was enrolled, from, until and why each enrollment ended', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $year = enrollmentYear();
    $sectionA = enrollmentSection($year, letter: 'A');
    $sectionB = enrollmentSection($year, letter: 'B');
    $student = enrollmentStudent();
    $first = enrollmentRow($student, $year, $sectionA, ['ends_on' => '2026-10-01', 'end_reason' => 'section_change', 'end_note' => 'Reorganización']);
    $second = enrollmentRow($student, $year, $sectionB, ['starts_on' => '2026-10-01', 'previous_id' => $first]);
    DB::table('enrollments')->where('id', $second)->update([
        'ends_on' => '2026-10-01', 'end_reason' => 'annulled',
        'annulment_reason' => 'duplicate', 'annulled_by' => $admin->id, 'annulled_at' => '2026-10-02 09:00:00-04',
    ]);

    $this->actingAs($admin)->get(tenantUrl($institution, "/estudiantes/{$student}"))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Students/Show')
        ->where('enrollments.0.gradeName', '3.er grado')
        ->where('enrollments.0.letter', 'A')
        ->where('enrollments.0.startsOn', '2026-09-16')
        ->where('enrollments.0.endsOn', '2026-10-01')
        ->where('enrollments.0.endReason', 'Cambio de sección')
        ->where('enrollments.0.endNote', 'Reorganización')
        ->where('enrollments.1.letter', 'B')
        ->where('enrollments.1.endReason', 'Anulada')
        ->where('enrollments.1.annulmentReason', 'Doble registro')
        ->where('enrollments.1.current', false));
});
