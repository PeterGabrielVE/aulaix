<?php

use AulaX\Workspace\Domain\Capabilities;
use AulaX\Workspace\Domain\MenuEntry;
use AulaX\Workspace\Domain\MenuGroup;

/**
 * SPEC-013 NAV-R03.1: what a person can do in the open institution, and the
 * one definition of the menu's entries.
 */
it('tells the permissions, roles and guardian link of a person', function () {
    $capabilities = new Capabilities(['ver-estructura'], ['Director', 'Docente'], 'Director', isGuardian: true);

    expect($capabilities->has('ver-estructura'))->toBeTrue()
        ->and($capabilities->has('gestionar-usuarios'))->toBeFalse()
        ->and($capabilities->hasRole('Docente'))->toBeTrue()
        ->and($capabilities->hasRole('Coordinador'))->toBeFalse()
        ->and($capabilities->primaryRole)->toBe('Director')
        ->and($capabilities->isGuardian)->toBeTrue();
});

it('knows the eleven entries of the menu, in order and by group', function () {
    expect(array_map(fn (MenuEntry $entry) => $entry->value, MenuEntry::cases()))->toBe([
        'home', 'calendar', 'structure', 'my_assignments', 'students', 'transfers', 'corrections', 'my_students',
        'institution', 'users', 'audit',
    ])
        ->and(MenuEntry::Home->group())->toBeNull()
        ->and(MenuEntry::Structure->group())->toBe(MenuGroup::Academic)
        ->and(MenuEntry::MyStudents->group())->toBe(MenuGroup::Students)
        ->and(MenuEntry::Audit->group())->toBe(MenuGroup::Institution)
        ->and(array_map(fn (MenuGroup $group) => $group->value, MenuGroup::cases()))->toBe(['academic', 'students', 'institution']);
});

it('shows each entry to whoever meets its condition', function (MenuEntry $entry, Capabilities $allowed, Capabilities $denied) {
    expect($entry->isVisibleTo($allowed))->toBeTrue()
        ->and($entry->isVisibleTo($denied))->toBeFalse();
})->with([
    'calendar' => [MenuEntry::Calendar, new Capabilities(['gestionar-calendario'], [], null), new Capabilities([], [], null)],
    'structure' => [MenuEntry::Structure, new Capabilities(['ver-estructura'], [], null), new Capabilities([], [], null)],
    'my assignments' => [MenuEntry::MyAssignments, new Capabilities([], ['Docente'], 'Docente'), new Capabilities(['ver-estructura'], ['Coordinador'], 'Coordinador')],
    'students' => [MenuEntry::Students, new Capabilities(['ver-estudiantes'], [], null), new Capabilities([], [], null)],
    'transfers' => [MenuEntry::Transfers, new Capabilities(['gestionar-estudiantes'], [], null), new Capabilities(['ver-estudiantes'], [], null)],
    'corrections' => [MenuEntry::Corrections, new Capabilities(['gestionar-estudiantes'], [], null), new Capabilities(['ver-estudiantes'], [], null)],
    'my students' => [MenuEntry::MyStudents, new Capabilities([], [], null, isGuardian: true), new Capabilities([], ['Representante'], 'Representante')],
    'institution' => [MenuEntry::Institution, new Capabilities(['ver-plantel'], [], null), new Capabilities([], [], null)],
    'users' => [MenuEntry::Users, new Capabilities(['gestionar-usuarios'], [], null), new Capabilities([], [], null)],
    'audit' => [MenuEntry::Audit, new Capabilities(['ver-bitacora'], [], null), new Capabilities([], [], null)],
]);

it('shows the home entry to every member', function () {
    expect(MenuEntry::Home->isVisibleTo(new Capabilities([], [], null)))->toBeTrue();
});
