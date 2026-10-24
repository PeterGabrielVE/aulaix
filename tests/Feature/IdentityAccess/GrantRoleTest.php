<?php

use AulaX\IdentityAccess\Application\Members\GrantRoleHandler;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\NotFound;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-009 EST-R08.7 (through SPEC-013): another module gives a member one
 * more role, under the same rules and audit as the users screen.
 */
function grantRole(object $actor, object $member, string $role): void
{
    test()->actingAs($actor);

    app(GrantRoleHandler::class)($actor->id, $member->id, $role);
}

test('adds the role to the ones the member has, and records it', function () {
    $institution = tenant();
    useTenant($institution);
    $director = userWithRole($institution, 'Director');
    $teacher = userWithRole($institution, 'Docente');

    grantRole($director, $teacher, 'Representante');

    $entry = DB::table('audit_entries')->where('event_type', 'identity.membership.roles_changed')->sole();

    expect($teacher->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Docente', 'Representante'])
        ->and(json_decode($entry->changes, true))->toEqualCanonicalizing(['roles' => ['from' => ['Docente'], 'to' => ['Docente', 'Representante']]])
        ->and($entry->actor_user_id)->toBe($director->id);
});

test('does nothing when the member already has the role', function () {
    $institution = tenant();
    useTenant($institution);
    $director = userWithRole($institution, 'Director');
    $guardian = userWithRole($institution, 'Representante');

    grantRole($director, $guardian, 'Representante');

    expect(DB::table('audit_entries')->count())->toBe(0);
});

test('nobody escalates to Administrador this way', function () {
    $institution = tenant();
    useTenant($institution);
    $director = userWithRole($institution, 'Director');
    $teacher = userWithRole($institution, 'Docente');

    expect(fn () => grantRole($director, $teacher, 'Administrador'))->toThrow(BusinessRuleViolations::class)
        ->and($teacher->fresh()->getRoleNames()->all())->toBe(['Docente']);
});

test('refuses someone who is not a member of the open institution', function () {
    $institution = tenant();
    $other = tenant();
    $outsider = userWithRole($other, 'Docente');
    useTenant($institution);
    $director = userWithRole($institution, 'Director');

    expect(fn () => grantRole($director, $outsider, 'Representante'))->toThrow(NotFound::class);
});
