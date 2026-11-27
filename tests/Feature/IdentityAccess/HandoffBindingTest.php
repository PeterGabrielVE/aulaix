<?php

use AulaX\IdentityAccess\Infrastructure\Auth\CookieBrowserBinding;

/**
 * SPEC-015 RLT-R04, NF05: a handoff link only opens the institution in the
 * browser that asked for it; anywhere else it is refused like an expired
 * one, without saying why and without touching another session.
 */
beforeEach(function () {
    $this->norte = tenant();
    $this->ana = userWithRole($this->norte, 'Docente', ['email' => 'ana@example.com']);
    $this->luis = userWithRole($this->norte, 'Docente', ['email' => 'luis@example.com']);
    leaveTenant();
});

/**
 * Ana logs in on the central domain and chooses her institution: the
 * handoff link, as her browser receives it.
 */
function anasHandoffLink(): string
{
    $location = (string) test()->post(centralUrl('/login?plantel='.test()->norte->subdomain), ['email' => 'ana@example.com', 'password' => 'password'])
        ->headers->get('Location');

    expect($location)->toContain('/auth/handoff?token=');

    // The subdomain has its own session: it starts empty.
    test()->flushSession();
    auth()->forgetGuards();

    return $location;
}

/**
 * Another browser: no cookie of Ana's, no session.
 */
function inAnotherBrowser(): void
{
    test()->forgetBrowserCookies();
    test()->flushSession();
    auth()->forgetGuards();
}

test('the browser that asked for it gets in', function () {
    $this->get(anasHandoffLink())->assertRedirect(tenantUrl($this->norte, '/dashboard'));

    $this->assertAuthenticatedAs($this->ana);
});

test('another browser is sent to the central login, with the institution chosen, and nobody gets in (RLT-R04.2)', function () {
    $link = anasHandoffLink();
    inAnotherBrowser();

    $this->get($link)->assertRedirect(centralUrl('/login?plantel='.$this->norte->subdomain));

    $this->assertGuest();
});

test('the refusal says nothing about why (RLT-R04.3, NF05)', function () {
    $link = anasHandoffLink();
    inAnotherBrowser();
    $refused = $this->get($link);

    $expired = $this->get(tenantUrl($this->norte, '/auth/handoff?token=caducado'));

    expect($refused->headers->get('Location'))->toBe($expired->headers->get('Location'))
        ->and(session('errors'))->toBeNull();
});

test('the owner can still use the link that someone else opened first', function () {
    $link = anasHandoffLink();
    $anasBrowser = test()->browserCookies();
    inAnotherBrowser();
    $this->get($link)->assertRedirect(centralUrl('/login?plantel='.$this->norte->subdomain));

    test()->withBrowserCookies($anasBrowser);
    $this->get($link)->assertRedirect(tenantUrl($this->norte, '/dashboard'));
    $this->assertAuthenticatedAs($this->ana);
});

test('a session of another account in the institution is not replaced (RLT-R04.4)', function () {
    $link = anasHandoffLink();
    $this->actingAs($this->luis);

    $this->get($link)->assertRedirect(centralUrl('/login?plantel='.$this->norte->subdomain));

    $this->assertAuthenticatedAs($this->luis);
});

test('the browser cookie is HttpOnly, shared by the subdomains, and lives with the browser session', function () {
    $response = $this->post(centralUrl('/login?plantel='.$this->norte->subdomain), ['email' => 'ana@example.com', 'password' => 'password']);
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === CookieBrowserBinding::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getDomain())->toBe('.'.config('app.domain'))
        ->and($cookie->getExpiresTime())->toBe(0)
        ->and($cookie->getSameSite())->toBe('lax');
});
