<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Only APP_DOMAIN and its subdomains reach the app; any other Host
        // header is rejected before routing. Laravel skips this in local and
        // testing environments.
        $middleware->trustHosts(at: fn () => [config('app.domain')], subdomains: true);

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'active' => EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Laravel's default middleware priority list runs Authenticate
        // before any route middleware not listed in it (like our custom
        // 'tenant' alias), regardless of the order they're attached in
        // routes/web.php. Without this, a guest hitting an `auth`-protected
        // tenant route gets redirected before ResolveTenant has set the
        // {tenant} URL default, and route('login') blows up with "Missing
        // required parameter: tenant".
        // Laravel's default priority list references the *contract*
        // (Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests), not
        // the concrete Authenticate class — passing the concrete class here
        // silently no-ops (the "before" target is never found, so the
        // middleware gets appended at the very end instead).
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
