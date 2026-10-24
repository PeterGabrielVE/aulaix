<?php

use App\Providers\AppServiceProvider;
use AulaX\AcademicCalendar\Infrastructure\AcademicCalendarServiceProvider;
use AulaX\AcademicStructure\Infrastructure\AcademicStructureServiceProvider;
use AulaX\Ai\Infrastructure\AiServiceProvider;
use AulaX\Audit\Infrastructure\AuditServiceProvider;
use AulaX\Catalogs\Infrastructure\CatalogsServiceProvider;
use AulaX\IdentityAccess\Infrastructure\IdentityAccessServiceProvider;
use AulaX\Shared\Infrastructure\SharedServiceProvider;
use AulaX\Students\Infrastructure\StudentsServiceProvider;
use AulaX\Tenancy\Infrastructure\TenancyServiceProvider;
use AulaX\Workspace\Infrastructure\WorkspaceServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AiServiceProvider::class,
    CatalogsServiceProvider::class,
    TenancyServiceProvider::class,
    AuditServiceProvider::class,
    IdentityAccessServiceProvider::class,
    AcademicCalendarServiceProvider::class,
    StudentsServiceProvider::class,
    AcademicStructureServiceProvider::class,
    WorkspaceServiceProvider::class,
];
