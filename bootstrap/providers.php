<?php

use App\Providers\AppServiceProvider;
use AulaX\Ai\Infrastructure\AiServiceProvider;
use AulaX\Catalogs\Infrastructure\CatalogsServiceProvider;
use AulaX\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AiServiceProvider::class,
    CatalogsServiceProvider::class,
];
