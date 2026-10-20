<?php

use App\Providers\AppServiceProvider;
use AulaX\AcademicCalendar\Infrastructure\AcademicCalendarServiceProvider;
use AulaX\Ai\Infrastructure\AiServiceProvider;
use AulaX\Audit\Infrastructure\AuditServiceProvider;
use AulaX\Catalogs\Infrastructure\CatalogsServiceProvider;
use AulaX\IdentityAccess\Infrastructure\IdentityAccessServiceProvider;
use AulaX\Shared\Infrastructure\SharedServiceProvider;
use AulaX\Tenancy\Infrastructure\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AiServiceProvider::class,
    CatalogsServiceProvider::class,
    TenancyServiceProvider::class,
    AuditServiceProvider::class,
    IdentityAccessServiceProvider::class,
    AcademicCalendarServiceProvider::class,
];
