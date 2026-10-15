<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Home;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Role\HomeScreen;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;

/**
 * Where a member lands after logging in to the active institution: the home
 * screen of their highest-priority role there (IAM-R12.2), or none when they
 * hold no role yet (IAM-R12.3).
 */
final readonly class ResolveHomeScreenHandler
{
    public function __construct(private RoleCatalog $roles) {}

    public function __invoke(int $accountId): ?HomeScreen
    {
        return $this->roles->rolesOf(AccountId::fromInt($accountId))->homeScreen();
    }
}
