<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Shared\Domain\Clock;

/**
 * Whether the operator typed a TOTP in the last 10 minutes, as sensitive
 * actions need (PLT-NF04).
 */
final readonly class RecentTotpQuery
{
    private const int WINDOW_SECONDS = 10 * 60;

    public function __construct(
        private OperatorSession $session,
        private Clock $clock,
    ) {}

    public function __invoke(): bool
    {
        $confirmedAt = $this->session->totpConfirmedAt();

        return $confirmedAt !== null
            && $this->clock->now()->getTimestamp() - $confirmedAt->getTimestamp() <= self::WINDOW_SECONDS;
    }
}
