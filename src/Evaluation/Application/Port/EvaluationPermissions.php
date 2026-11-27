<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Port;

/**
 * What the signed-in person may do with grades in the open institution
 * (S-04). Which subjects and sections are theirs is TeachingFacts' to tell.
 */
interface EvaluationPermissions
{
    /**
     * gestionar-calificaciones: grades the subjects it teaches or the
     * sections it guides (EVA-R07.1, R07.2).
     */
    public function canGrade(): bool;

    /**
     * ver-calificaciones.
     */
    public function canView(): bool;

    /**
     * gestionar-estudiantes: sees every grade of the institution (EVA-R07.4).
     */
    public function seesAll(): bool;

    /**
     * editar-notas-lapso-cerrado (CAL-R05.2).
     */
    public function canEditClosedTerms(): bool;

    public function currentAccountId(): ?int;

    /**
     * gestionar-estructura: assigns the teachers of the groups of pending
     * subjects (SPEC-016 Q1).
     */
    public function canAssignPendingTeachers(): bool;
}
