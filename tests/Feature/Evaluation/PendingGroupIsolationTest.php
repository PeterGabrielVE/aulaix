<?php

use AulaX\Evaluation\Application\PendingGroups\PendingGroupSheetQuery;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupPlanCommand;
use AulaX\Evaluation\Application\PendingGroups\SaveGroupPlanHandler;
use AulaX\Evaluation\Domain\ActivityInput;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

pest()->group('rls');

/**
 * SPEC-016 RVP-R08.1 – R08.3, NF02: a group of pending subjects of another
 * institution does not exist: it is not read, planned nor graded.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->group = DB::table('pending_groups')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->math, 'origin_grade' => 'media_2', 'teacher_account_id' => $this->teacher->id]);
});

it('another institution does not find its group of pending subjects', function () {
    $this->actingAs(userWithRole(tenant(), 'Administrador'));

    expect(app(PendingGroupRepository::class)->groupById($this->group))->toBeNull()
        ->and(fn () => app(PendingGroupSheetQuery::class)($this->group, 1))->toThrow(PendingGroupNotFound::class);
});

it('another institution can not plan its group of pending subjects', function () {
    $this->actingAs(userWithRole(tenant(), 'Docente'));

    expect(fn () => app(SaveGroupPlanHandler::class)(new SaveGroupPlanCommand($this->group, 1, [new ActivityInput(null, 'Prueba', '2026-10-05', '100')], false)))
        ->toThrow(PendingGroupNotFound::class);
});
