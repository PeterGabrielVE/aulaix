<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\InstitutionFiles;

use AulaX\Shared\Application\PlatformReadScope;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Tenancy\Application\Port\InstitutionFiles;

/**
 * For the platform (SPEC-014 PLT-R04.3, V2): the bytes the open
 * institution's files take. Only inside the platform's bounded read.
 */
final readonly class UsedStorageQuery
{
    public function __construct(private InstitutionFiles $files) {}

    /**
     * @throws AuthorizationDenied
     */
    public function __invoke(): int
    {
        PlatformReadScope::ensureActive();

        return $this->files->usedBytes();
    }
}
