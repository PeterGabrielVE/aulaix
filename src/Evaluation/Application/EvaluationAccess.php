<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application;

use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The fine checks of every use case (S-04, EVA-NF01): the permission, the
 * assignment of the person signed in (EVA-R07.1, R07.2) and whether the
 * term takes grades (EVA-R06, CAL-R05).
 */
final readonly class EvaluationAccess
{
    public function __construct(
        private EvaluationPermissions $permissions,
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
    ) {}

    /**
     * @throws AuthorizationDenied
     */
    public function ensureTeaches(int $sectionId, int $subjectId): int
    {
        $account = $this->grader();

        if (! $this->teaching->teaches($account, $sectionId, $subjectId)) {
            throw new AuthorizationDenied('No dictas esa materia en esa sección.');
        }

        return $account;
    }

    /**
     * Only the teacher assigned to a group of pending subjects plans and
     * grades it (RVP-R04.5).
     *
     * @throws AuthorizationDenied
     */
    public function ensureAssigned(PendingGroup $group): int
    {
        $account = $this->grader();

        if ($group->teacherAccountId() !== $account) {
            throw new AuthorizationDenied('No tienes asignado ese grupo de pendientes.');
        }

        return $account;
    }

    /**
     * @throws AuthorizationDenied
     */
    public function ensureGuides(int $sectionId): int
    {
        $account = $this->grader();

        if (! $this->teaching->guides($account, $sectionId)) {
            throw new AuthorizationDenied('No eres el guía de esa sección.');
        }

        return $account;
    }

    /**
     * @return bool Whether the change happens in a closed term, which the
     *              audit log records with who made it (EVA-R06.3).
     *
     * @throws NotEditable
     */
    public function ensureEditable(int $schoolYearId, int $term): bool
    {
        $status = $this->calendar->termStatus($schoolYearId, $term);

        if ($status !== 'open' && $status !== 'closed') {
            throw NotEditable::notOpened();
        }

        if ($this->calendar->gradesEditable($schoolYearId, $term, $this->permissions->canEditClosedTerms())) {
            return $status === 'closed';
        }

        throw $status === 'closed' && $this->permissions->canEditClosedTerms() ? NotEditable::yearClosed() : NotEditable::termClosed();
    }

    /**
     * @throws AuthorizationDenied
     */
    public function ensureCanView(): int
    {
        if (! $this->permissions->canView()) {
            throw new AuthorizationDenied('No tienes permiso para ver notas.');
        }

        return $this->permissions->currentAccountId() ?? throw new AuthorizationDenied('No tienes permiso para ver notas.');
    }

    private function grader(): int
    {
        if (! $this->permissions->canGrade()) {
            throw new AuthorizationDenied('No tienes permiso para cargar notas.');
        }

        return $this->permissions->currentAccountId() ?? throw new AuthorizationDenied('No tienes permiso para cargar notas.');
    }
}
