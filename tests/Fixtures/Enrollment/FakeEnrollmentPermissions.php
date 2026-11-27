<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\Enrollment\Application\Port\EnrollmentPermissions;

/**
 * Permissions a test switches at will.
 */
final class FakeEnrollmentPermissions implements EnrollmentPermissions
{
    public function __construct(
        public bool $manage = true,
        public bool $approve = true,
        public bool $view = true,
        public bool $seesAll = true,
        public ?int $accountId = 3,
    ) {}

    public function canManage(): bool
    {
        return $this->manage;
    }

    public function canApproveExceptions(): bool
    {
        return $this->approve;
    }

    public function canView(): bool
    {
        return $this->view;
    }

    public function seesAllStudents(): bool
    {
        return $this->seesAll;
    }

    public function currentAccountId(): ?int
    {
        return $this->accountId;
    }
}
