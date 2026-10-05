<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
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
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\ResolveTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
