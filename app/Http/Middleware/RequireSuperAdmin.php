<?php

namespace App\Http\Middleware;

use AulaX\Platform\Application\Access\CurrentOperator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The coarse check of the panel's management routes (SPEC-014 PLT-NF05);
 * the use cases check again. Runs after «platform», which sets the operator.
 */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $operator = $request->attributes->get('platform.operator');

        abort_unless($operator instanceof CurrentOperator && $operator->role === 'super_admin', 403);

        return $next($request);
    }
}
