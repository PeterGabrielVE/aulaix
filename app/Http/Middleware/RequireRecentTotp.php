<?php

namespace App\Http\Middleware;

use AulaX\Platform\Application\Access\RecentTotpQuery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sensitive actions of the panel need a TOTP typed in the last 10 minutes
 * (SPEC-014 PLT-NF04); otherwise, confirm one and come back.
 */
class RequireRecentTotp
{
    public function __construct(private readonly RecentTotpQuery $recentTotp) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (($this->recentTotp)()) {
            return $next($request);
        }

        $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : url()->previous());

        return redirect()->route('platform.totp.confirm');
    }
}
