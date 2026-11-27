<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

/**
 * Authorization facts for SPEC-012 Documents (DOC-R08.1 – R08.3, S-04).
 */
interface DocumentPermissions
{
    public function canIssue(): bool;

    /**
     * True for Administrador, Director, or any member granted emitir-documentos
     * without being restricted to Coordinator grade scope.
     */
    public function hasFullScope(): bool;

    public function currentAccountId(): ?int;
}
