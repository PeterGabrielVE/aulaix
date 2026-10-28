<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

/**
 * A random secret of the browser, shared by the central domain and every
 * subdomain (SPEC-015 RLT-R04): a handoff link is only good in the browser
 * that asked for it, so nobody can be logged in to someone else's session
 * by following their link.
 */
interface BrowserBinding
{
    /**
     * The secret of this browser, created the first time it is needed.
     */
    public function secret(): string;

    /**
     * The secret this browser sent, or null when it has none.
     */
    public function presented(): ?string;

    /**
     * A new secret for this browser: links issued before stop working.
     */
    public function rotate(): void;
}
