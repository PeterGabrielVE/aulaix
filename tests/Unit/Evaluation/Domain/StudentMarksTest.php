<?php

use AulaX\Evaluation\Domain\Literal;
use AulaX\Evaluation\Domain\MarkChange;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\MarkNotValid;
use AulaX\Evaluation\Domain\StudentMarks;

/**
 * SPEC-011 EVA-R04.1 – R04.4, R05.1, R05.2, R10.2, R12.3: the literals of
 * Primaria (three terms and the final one) and the reports of Inicial of a
 * student in a school year.
 */
function marksOfAna(): StudentMarks
{
    return StudentMarks::of(1, 2, []);
}

function changes(array $changes): array
{
    return array_map(fn (MarkChange $change) => [$change->field, $change->before, $change->after], $changes);
}

it('takes a literal from A to E, in either case', function () {
    expect(Literal::fromInput(' b '))->toBe(Literal::B)
        ->and(fn () => Literal::fromInput('F'))->toThrow(MarkNotValid::class, 'El literal debe ser A, B, C, D o E.')
        ->and(fn () => Literal::fromInput(''))->toThrow(MarkNotValid::class);
});

it('records the literal of a term with its description, and tells what changed', function () {
    $marks = marksOfAna();

    expect(changes($marks->recordLiteral(1, Literal::B, 'Lee con fluidez.')))->toBe([['literal_1', null, 'B'], ['description_1', null, 'escrita']])
        ->and($marks->markOf('1', MarkKind::Literal)?->literal)->toBe(Literal::B)
        ->and($marks->markOf('1', MarkKind::Literal)?->description)->toBe('Lee con fluidez.')
        ->and(changes($marks->recordLiteral(1, Literal::B, 'Lee con fluidez.')))->toBe([])
        ->and(changes($marks->recordLiteral(1, Literal::A, null)))->toBe([['literal_1', 'B', 'A'], ['description_1', 'escrita', null]]);
});

it('refuses a description longer than 5000 characters', function () {
    expect(fn () => marksOfAna()->recordLiteral(1, Literal::A, str_repeat('a', 5001)))->toThrow(MarkNotValid::class, 'La descripción tiene como máximo 5000 caracteres.');
});

it('records the final literal only once the third term is closed', function () {
    $marks = marksOfAna();

    expect(fn () => $marks->recordFinalLiteral(Literal::C, null, false))->toThrow(MarkNotValid::class, 'El literal final se registra con el tercer lapso cerrado.')
        ->and(changes($marks->recordFinalLiteral(Literal::C, null, true)))->toBe([['literal_final', null, 'C']]);
});

it('records a report of 1 to 5000 characters, never its text in what changed', function () {
    $marks = marksOfAna();

    expect(fn () => $marks->recordReport(2, '   '))->toThrow(MarkNotValid::class, 'El informe debe tener entre 1 y 5000 caracteres.')
        ->and(fn () => $marks->recordReport(2, str_repeat('a', 5001)))->toThrow(MarkNotValid::class)
        ->and(changes($marks->recordReport(2, 'Juega con sus compañeros.')))->toBe([['report_2', null, 'escrito']])
        ->and($marks->markOf('2', MarkKind::Report)?->description)->toBe('Juega con sus compañeros.')
        ->and(changes($marks->recordReport(2, 'Comparte sus juguetes.')))->toBe([['report_2', 'escrito', 'escrito']])
        ->and(changes($marks->recordReport(2, 'Comparte sus juguetes.')))->toBe([]);
});

it('marks as incomplete only what is missing, and a later mark replaces it', function () {
    $marks = marksOfAna();
    $marks->recordLiteral(1, Literal::A, null);

    expect($marks->fillIncomplete('1', MarkKind::Literal))->toBeFalse()
        ->and($marks->fillIncomplete('2', MarkKind::Literal))->toBeTrue()
        ->and($marks->markOf('2', MarkKind::Literal)?->incomplete)->toBeTrue()
        ->and($marks->isMissing('2', MarkKind::Literal))->toBeFalse()
        ->and($marks->isMissing('3', MarkKind::Literal))->toBeTrue()
        ->and(changes($marks->recordLiteral(2, Literal::D, null)))->toBe([['literal_2', 'incompleto', 'D']])
        ->and($marks->markOf('2', MarkKind::Literal)?->incomplete)->toBeFalse();
});
