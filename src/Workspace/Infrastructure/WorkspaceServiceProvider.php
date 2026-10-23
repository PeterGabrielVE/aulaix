<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\Workspace\Application\Port\CalendarSource;
use AulaX\Workspace\Application\Port\ViewerCapabilities;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Workspace module's ports to their adapters (constitution A-07).
 * Workspace has no tables nor writes (ADR 0009): every adapter reads through
 * another module's Application layer.
 */
final class WorkspaceServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CalendarSource::class => AcademicCalendarSource::class,
    ];

    public function register(): void
    {
        // One per request: the layout and the home screen share what it loads.
        $this->app->scoped(ViewerCapabilities::class, GateViewerCapabilities::class);
    }
}
