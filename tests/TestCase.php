<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Relative-path requests (e.g. $this->get('/')) hit the central domain
     * by default. Tenant-domain requests use the tenantUrl() test helper
     * (tests/Pest.php) to build an absolute {subdomain}.{domain} URL instead.
     */
    protected $baseUrl = 'http://aulaix.test';
}
