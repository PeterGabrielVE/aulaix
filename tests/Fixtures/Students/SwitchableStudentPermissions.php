<?php

namespace Tests\Fixtures\Students;

use AulaX\Students\Application\Port\StudentPermissions;

/**
 * Permissions a test turns on and off.
 */
final class SwitchableStudentPermissions implements StudentPermissions
{
    public function __construct(public bool $manage = true, public bool $view = true) {}

    public function canManage(): bool
    {
        return $this->manage;
    }

    public function canView(): bool
    {
        return $this->view;
    }
}
