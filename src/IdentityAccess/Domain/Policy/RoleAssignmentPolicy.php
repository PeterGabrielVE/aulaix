<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Policy;

use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Who may give or take which roles (IAM-R10), so an institution can never be
 * left without anyone managing it, and nobody escalates to Administrador.
 * Every broken rule is reported at once, as the form always did.
 */
final class RoleAssignmentPolicy
{
    /**
     * @param  RoleSet  $managementRoles  The institution's roles that carry the gestionar-usuarios permission.
     * @return list<BusinessRuleViolation>
     */
    public function violations(AssignmentRequest $request, RoleSet $managementRoles): array
    {
        $violations = [];
        $actorIsAdministrador = $request->actorRoles->has(BaseRole::Administrador);

        if ($request->editingSelf) {
            if ($actorIsAdministrador && ! $request->requestedRoles->has(BaseRole::Administrador)) {
                $violations[] = BusinessRuleViolation::onField('roles', 'No puedes quitarte a ti mismo el rol de Administrador.');
            } elseif (! $request->requestedRoles->intersects($managementRoles)) {
                $violations[] = BusinessRuleViolation::onField('roles', 'No puedes quitarte a ti mismo la gestión de usuarios.');
            }
        }

        $wantsAdministrador = $request->requestedRoles->has(BaseRole::Administrador);
        $isAdministrador = (bool) $request->targetRoles?->has(BaseRole::Administrador);

        if (! $actorIsAdministrador && $wantsAdministrador !== $isAdministrador) {
            $violations[] = BusinessRuleViolation::onField('roles', 'Solo un Administrador puede asignar o quitar el rol de Administrador.');
        }

        if ($request->editingSelf && ! $request->requestedActive) {
            $violations[] = BusinessRuleViolation::onField('status', 'No puedes desactivar tu propia cuenta.');
        }

        return $violations;
    }

    /**
     * CAL-R05.4: the permission to change grades of closed terms is granted
     * person by person, only by an Administrador, as the Administrador role
     * itself is (IAM-R10.1).
     */
    public function closedTermGradeEditing(RoleSet $actorRoles): ?BusinessRuleViolation
    {
        return $actorRoles->has(BaseRole::Administrador)
            ? null
            : BusinessRuleViolation::onField('closed_term_grades', 'Solo un Administrador puede conceder o retirar el permiso de editar notas de lapsos cerrados.');
    }

    /**
     * SPEC-010 MAT-R11.3: managing enrollments is granted to other members
     * person by person, only by an Administrador.
     */
    public function enrollmentManagementGranting(RoleSet $actorRoles): ?BusinessRuleViolation
    {
        return $actorRoles->has(BaseRole::Administrador)
            ? null
            : BusinessRuleViolation::onField('enrollment_management', 'Solo un Administrador puede conceder o retirar el permiso de gestionar inscripciones.');
    }
}
