<?php

use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-011 EVA-R06.1, R06.2, R06.4, R07.1, R07.2, R07.6, NF01: who grades
 * what, and when a term takes grades.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
});

function access(): EvaluationAccess
{
    $scene = test()->scene;

    return new EvaluationAccess($scene->permissions, $scene->teaching, $scene->calendar);
}

it('grades a subject only whoever teaches it in that section, with gestionar-calificaciones', function () {
    $this->scene->teaching->teaching[] = [9, 10, 4];

    expect(fn () => access()->ensureTeaches(10, 4))->not->toThrow(AuthorizationDenied::class)
        ->and(fn () => access()->ensureTeaches(10, 5))->toThrow(AuthorizationDenied::class, 'No dictas esa materia en esa sección.')
        ->and(fn () => access()->ensureTeaches(11, 4))->toThrow(AuthorizationDenied::class, 'No dictas esa materia en esa sección.');

    $this->scene->permissions->grade = false;

    expect(fn () => access()->ensureTeaches(10, 4))->toThrow(AuthorizationDenied::class, 'No tienes permiso para cargar notas.');
});

it('grades literals and reports only the guide of the section', function () {
    $this->scene->teaching->guiding[] = [9, 10];

    expect(fn () => access()->ensureGuides(10))->not->toThrow(AuthorizationDenied::class)
        ->and(fn () => access()->ensureGuides(11))->toThrow(AuthorizationDenied::class, 'No eres el guía de esa sección.');
});

it('takes grades in an open term; a closed one only with the special permission, never in a closed year, never one not opened yet', function () {
    $this->scene->calendar->terms[1]['status'] = 'open';
    $this->scene->calendar->terms[2]['status'] = 'closed';

    expect(fn () => access()->ensureEditable(2, 1))->not->toThrow(NotEditable::class)
        ->and(fn () => access()->ensureEditable(2, 2))->toThrow(NotEditable::class, 'El lapso está cerrado: sus notas no cambian.')
        ->and(fn () => access()->ensureEditable(2, 3))->toThrow(NotEditable::class, 'El lapso aún no se abre.');

    $this->scene->permissions->editClosedTerms = true;

    expect(access()->ensureEditable(2, 2))->toBeTrue();

    $this->scene->calendar->yearClosed = true;

    expect(fn () => access()->ensureEditable(2, 2))->toThrow(NotEditable::class, 'El año escolar está cerrado: sus notas no cambian.');
});

it('tells whether a change happens in a closed term', function () {
    $this->scene->calendar->terms[1]['status'] = 'open';

    expect(access()->ensureEditable(2, 1))->toBeFalse();
});
