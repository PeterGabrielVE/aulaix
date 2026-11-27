<?php

declare(strict_types=1);

namespace AulaX\Documents\Domain;

/**
 * Scope rules for document issuers (SPEC-012 DOC-R08.3, S-04):
 * - Administrador, Director, or a member granted emitir-documentos individually
 *   without Coordinator restriction has full institution scope.
 * - A Coordinator (who is neither Administrador nor Director) is restricted to
 *   the grades they coordinate (EAC-R06).
 */
final readonly class DocumentScopePolicy
{
    /**
     * @param  list<string>  $coordinatedGrades
     */
    public function canAccessGrade(bool $hasFullScope, array $coordinatedGrades, string $gradeCode): bool
    {
        return $hasFullScope || in_array($gradeCode, $coordinatedGrades, true);
    }

    /**
     * @param  list<string>  $coordinatedGrades
     * @param  list<string>  $studentGradeCodes
     */
    public function canAccessAnyGrade(bool $hasFullScope, array $coordinatedGrades, array $studentGradeCodes): bool
    {
        if ($hasFullScope) {
            return true;
        }

        foreach ($studentGradeCodes as $gradeCode) {
            if (in_array($gradeCode, $coordinatedGrades, true)) {
                return true;
            }
        }

        return false;
    }
}
