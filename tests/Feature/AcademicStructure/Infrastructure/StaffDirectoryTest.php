<?php

use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-008 EAC-R05.2, R06.2, R06.4, R12.3: who may teach, guide or
 * coordinate: an active member of the open institution with that role, by
 * any of their roles.
 */
it('tells whether a person is an active member with a role', function () {
    $institution = tenant();
    $teacher = userWithRole($institution, 'Docente');
    $director = userWithRole($institution, 'Director');
    $teachingDirector = userWithRole($institution, 'Director');
    $teachingDirector->assignRole('Docente');
    $suspended = userWithRole($institution, 'Docente');
    DB::table('institution_memberships')->where('user_id', $suspended->id)->update(['status' => 'suspended']);
    $staff = app(StaffDirectory::class);

    expect($staff->isActiveWithRole($teacher->id, 'Docente'))->toBeTrue()
        ->and($staff->isActiveWithRole($director->id, 'Docente'))->toBeFalse()
        ->and($staff->isActiveWithRole($teachingDirector->id, 'Docente'))->toBeTrue()
        ->and($staff->isActiveWithRole($suspended->id, 'Docente'))->toBeFalse();
});

it('does not take a member of another institution', function () {
    $other = tenant();
    $foreign = userWithRole($other, 'Docente');
    tenant();

    expect(app(StaffDirectory::class)->isActiveWithRole($foreign->id, 'Docente'))->toBeFalse();
});

it('lists the active members with a role, by name', function () {
    $institution = tenant();
    $beto = userWithRole($institution, 'Docente', ['name' => 'Beto Rojas']);
    $ana = userWithRole($institution, 'Docente', ['name' => 'Ana Pérez']);
    userWithRole($institution, 'Coordinador', ['name' => 'Carla Coordinadora']);

    expect(app(StaffDirectory::class)->candidates('Docente'))->toBe([
        ['id' => $ana->id, 'name' => 'Ana Pérez'],
        ['id' => $beto->id, 'name' => 'Beto Rojas'],
    ]);
});

it('names the people assigned, telling who is no longer an active member', function () {
    $institution = tenant();
    $active = userWithRole($institution, 'Docente', ['name' => 'Ana Pérez']);
    $gone = userWithRole($institution, 'Docente', ['name' => 'Luis Mora']);
    DB::table('institution_memberships')->where('user_id', $gone->id)->update(['status' => 'ended']);

    expect(app(StaffDirectory::class)->describe([$active->id, $gone->id, 999_999]))->toBe([
        $active->id => ['name' => 'Ana Pérez', 'active' => true],
        $gone->id => ['name' => 'Luis Mora', 'active' => false],
        999_999 => ['name' => 'Persona sin acceso', 'active' => false],
    ]);
});
