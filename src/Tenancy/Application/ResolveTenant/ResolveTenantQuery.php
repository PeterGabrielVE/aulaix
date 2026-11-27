<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ResolveTenant;

final readonly class ResolveTenantQuery
{
    /**
     * @param  string  $subdomain  As captured from the request's host, unvalidated.
     */
    public function __construct(public string $subdomain) {}
}
