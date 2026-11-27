<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\SecurityLog;
use Psr\Log\LoggerInterface;

final readonly class LaravelSecurityLog implements SecurityLog
{
    public function __construct(private LoggerInterface $logger) {}

    public function loginFailed(?int $operatorId, string $reason): void
    {
        $this->logger->warning('platform.login_failed', ['operator_id' => $operatorId, 'reason' => $reason]);
    }
}
