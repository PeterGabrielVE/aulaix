<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure;

use AulaX\IdentityAccess\Application\Consolidation\ConsolidationOperators;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * The allowlist in config/identity.php (IDENTITY_CONSOLIDATION_OPERATORS).
 */
final readonly class ConfigConsolidationOperators implements ConsolidationOperators
{
    public function __construct(private Config $config) {}

    public function isAuthorized(string $operator): bool
    {
        return in_array($operator, (array) $this->config->get('identity.consolidation_operators', []), true);
    }
}
