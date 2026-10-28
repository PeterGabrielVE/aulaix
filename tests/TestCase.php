<?php

namespace Tests;

use AulaX\IdentityAccess\Infrastructure\Auth\CookieBrowserBinding;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Relative-path requests (e.g. $this->get('/')) hit the central domain
     * by default. Tenant-domain requests use the tenantUrl() test helper
     * (tests/Pest.php) to build an absolute {subdomain}.{domain} URL instead.
     */
    protected $baseUrl = 'http://aulaix.test';

    /**
     * SPEC-015 RLT-R04: like a browser, keep the cookie that binds handoff
     * links to it (shared by every subdomain) from one request to the next.
     * It arrives already encrypted, so it is sent back as is.
     *
     * {@inheritdoc}
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === CookieBrowserBinding::COOKIE) {
                $cookie->isCleared()
                    ? $this->forgetBrowserCookies()
                    : $this->unencryptedCookies[CookieBrowserBinding::COOKIE] = $cookie->getValue();
            }
        }

        return $response;
    }

    /**
     * Another browser: without the handoff binding cookie.
     */
    public function forgetBrowserCookies(): static
    {
        unset($this->unencryptedCookies[CookieBrowserBinding::COOKIE]);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function browserCookies(): array
    {
        return array_intersect_key($this->unencryptedCookies, [CookieBrowserBinding::COOKIE => true]);
    }

    /**
     * @param  array<string, string>  $cookies
     */
    public function withBrowserCookies(array $cookies): static
    {
        $this->unencryptedCookies = [...$this->unencryptedCookies, ...$cookies];

        return $this;
    }
}
