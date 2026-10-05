<?php

namespace App\Support;

use App\Models\Institution;

/**
 * Holds the institution resolved for the current request (set once, in
 * App\Http\Middleware\ResolveTenant). Bound as a singleton so every part of
 * the request lifecycle — the Eloquent tenant scope, controllers, Blade/
 * Inertia shared data — sees the same tenant without re-resolving it.
 */
class CurrentTenant
{
    private ?Institution $institution = null;

    public function set(Institution $institution): void
    {
        $this->institution = $institution;
    }

    public function get(): ?Institution
    {
        return $this->institution;
    }

    public function id(): ?int
    {
        return $this->institution?->id;
    }

    public function check(): bool
    {
        return $this->institution !== null;
    }
}
