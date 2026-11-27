<?php

namespace Tests\Fixtures\Workspace;

use AulaX\Workspace\Application\Port\ViewerCapabilities;
use AulaX\Workspace\Application\Viewer;

/**
 * The viewers of a test, by account id.
 */
final class FixedViewerCapabilities implements ViewerCapabilities
{
    /**
     * @param  array<int, Viewer>  $viewers
     */
    public function __construct(private array $viewers) {}

    public function of(int $accountId): Viewer
    {
        return $this->viewers[$accountId];
    }
}
