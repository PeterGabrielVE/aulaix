<?php

namespace Tests\Fixtures\Evaluation;

use AulaX\Evaluation\Application\Port\EvaluationPermissions;

/**
 * Permissions a test switches at will; the person signed in is account 9.
 */
final class FakeEvaluationPermissions implements EvaluationPermissions
{
    public function __construct(
        public bool $grade = true,
        public bool $view = true,
        public bool $seesAll = false,
        public bool $editClosedTerms = false,
        public ?int $accountId = 9,
        public bool $assignPendingTeachers = false,
    ) {}

    public function canAssignPendingTeachers(): bool
    {
        return $this->assignPendingTeachers;
    }

    public function canGrade(): bool
    {
        return $this->grade;
    }

    public function canView(): bool
    {
        return $this->view;
    }

    public function seesAll(): bool
    {
        return $this->seesAll;
    }

    public function canEditClosedTerms(): bool
    {
        return $this->editClosedTerms;
    }

    public function currentAccountId(): ?int
    {
        return $this->accountId;
    }
}
