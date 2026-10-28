<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Auth;

use AulaX\IdentityAccess\Application\Port\BrowserBinding;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Http\Request;

/**
 * The browser's secret in a cookie of the parent domain (.APP_DOMAIN), so
 * every subdomain receives it while each keeps its own session cookie
 * (SESSION_DOMAIN=null). HttpOnly, SameSite=Lax, encrypted like every
 * cookie, and gone when the browser closes. A secret created or rotated
 * during a request is kept on that request, for the rest of it.
 */
final readonly class CookieBrowserBinding implements BrowserBinding
{
    public const string COOKIE = 'aulax_browser';

    private const string ATTRIBUTE = 'identity.browser_secret';

    public function __construct(
        private Container $container,
        private CookieJar $cookies,
    ) {}

    public function secret(): string
    {
        $request = $this->request();

        return $request->attributes->get(self::ATTRIBUTE) ?? $this->presented() ?? $this->issue($request);
    }

    public function presented(): ?string
    {
        $secret = $this->request()->cookie(self::COOKIE);

        return is_string($secret) && strlen($secret) === 64 ? $secret : null;
    }

    public function rotate(): void
    {
        $this->issue($this->request());
    }

    private function issue(Request $request): string
    {
        $secret = bin2hex(random_bytes(32));
        $request->attributes->set(self::ATTRIBUTE, $secret);

        $this->cookies->queue($this->cookies->make(
            self::COOKIE,
            $secret,
            minutes: 0,
            path: '/',
            domain: '.'.config('app.domain'),
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $secret;
    }

    private function request(): Request
    {
        return $this->container->make('request');
    }
}
