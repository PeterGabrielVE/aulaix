<?php

use AulaX\IdentityAccess\Domain\Policy\AssignmentRequest;
use AulaX\IdentityAccess\Domain\Policy\RoleAssignmentPolicy;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * IAM-R10: the rules that used to live in closures of UserRequest. Messages
 * and field keys are the ones the user management screens show today.
 */
const NOT_ADMIN_ROLE = 'Solo un Administrador puede asignar o quitar el rol de Administrador.';
const KEEP_OWN_ADMIN = 'No puedes quitarte a ti mismo el rol de Administrador.';
const KEEP_OWN_MANAGEMENT = 'No puedes quitarte a ti mismo la gestión de usuarios.';
const STAY_ACTIVE = 'No puedes desactivar tu propia cuenta.';

/**
 * @return list<array{0: ?string, 1: string}> field and message of each violation
 */
function violationsOf(AssignmentRequest $request): array
{
    return array_map(
        fn (BusinessRuleViolation $violation) => [$violation->field(), $violation->getMessage()],
        (new RoleAssignmentPolicy)->violations($request, managementRoles: RoleSet::of('Administrador', 'Director')),
    );
}

function assignment(array $actorRoles, array $requested, ?array $targetRoles = null, bool $self = false, bool $active = true): AssignmentRequest
{
    return new AssignmentRequest(
        actorRoles: RoleSet::of(...$actorRoles),
        targetRoles: $targetRoles === null ? null : RoleSet::of(...$targetRoles),
        requestedRoles: RoleSet::of(...$requested),
        editingSelf: $self,
        requestedActive: $active,
    );
}

it('lets an administrador give any role, including administrador', function () {
    expect(violationsOf(assignment(['Administrador'], ['Administrador', 'Docente'])))->toBe([]);
});

it('stops a director from making anyone an administrador', function () {
    expect(violationsOf(assignment(['Director'], ['Administrador'])))->toBe([['roles', NOT_ADMIN_ROLE]]);
});

it('stops a director from taking the administrador role away', function () {
    expect(violationsOf(assignment(['Director'], ['Docente'], targetRoles: ['Administrador'])))->toBe([['roles', NOT_ADMIN_ROLE]]);
});

it('lets a director edit an administrador who keeps that role', function () {
    expect(violationsOf(assignment(['Director'], ['Administrador', 'Docente'], targetRoles: ['Administrador'])))->toBe([]);
});

it('stops an administrador from giving up their own administrador role', function () {
    expect(violationsOf(assignment(['Administrador'], ['Director'], targetRoles: ['Administrador'], self: true)))->toBe([['roles', KEEP_OWN_ADMIN]]);
});

it('stops a director from giving up managing users', function () {
    expect(violationsOf(assignment(['Director'], ['Docente'], targetRoles: ['Director'], self: true)))->toBe([['roles', KEEP_OWN_MANAGEMENT]]);
});

it('lets an administrador add roles to themselves', function () {
    expect(violationsOf(assignment(['Administrador'], ['Administrador', 'Docente'], targetRoles: ['Administrador'], self: true)))->toBe([]);
});

it('stops anyone from deactivating themselves', function () {
    expect(violationsOf(assignment(['Administrador'], ['Administrador'], targetRoles: ['Administrador'], self: true, active: false)))
        ->toBe([['status', STAY_ACTIVE]]);
});

it('reports every broken rule at once, as the form did', function () {
    expect(violationsOf(assignment(['Director'], ['Docente'], targetRoles: ['Director'], self: true, active: false)))
        ->toBe([['roles', KEEP_OWN_MANAGEMENT], ['status', STAY_ACTIVE]]);
});
