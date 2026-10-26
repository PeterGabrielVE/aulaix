<?php

namespace App\Http\Middleware;

use AulaX\Platform\Application\Access\CurrentOperatorQuery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's routes need a live operator session (SPEC-014 PLT-R01.4,
 * R01.8, R03.4); the rules live in CurrentOperatorQuery.
 */
class EnsurePlatformOperator
{
    public function __construct(private readonly CurrentOperatorQuery $currentOperator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $operator = ($this->currentOperator)();

        if ($operator === null) {
            return redirect()->guest(route('platform.login'));
        }

        $request->attributes->set('platform.operator', $operator);

        return $next($request);
    }
}
