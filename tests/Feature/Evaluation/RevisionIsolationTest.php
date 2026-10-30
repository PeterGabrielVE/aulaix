<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Revision\SaveRevisionCommand;
use AulaX\Evaluation\Application\Revision\SaveRevisionHandler;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

pest()->group('rls');

/**
 * SPEC-016 RVP-R08.1 – R08.3, NF02: an institution sees and changes only its
 * own revisions and groups of pending subjects; those of another one do not
 * exist for it.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->group = DB::table('pending_groups')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'origin_grade' => 'media_2']);
    revisionRow($this->year, $this->section, $this->math, $this->luis);
});

it('another institution sees none of its revisions nor groups of pending subjects', function () {
    $this->actingAs(userWithRole(tenant(), 'Administrador'));

    expect(DB::table('grade_revisions')->count())->toBe(0)
        ->and(DB::table('pending_groups')->count())->toBe(0);
});

it('another institution can not record a revision in its section', function () {
    $sur = tenant();
    $this->actingAs(userWithRole($sur, 'Docente'));

    expect(fn () => app(SaveRevisionHandler::class)(new SaveRevisionCommand($this->year, $this->section, $this->math, [$this->luis => '12'])))
        ->toThrow(EvaluationNotFound::class);
});
