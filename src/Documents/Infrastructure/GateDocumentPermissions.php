<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure;

use AulaX\Documents\Application\Port\DocumentPermissions;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * Checks `emitir-documentos` via Laravel's Gate and determines whether the
 * signed-in member has full institution scope or is restricted to coordinated
 * grades (SPEC-012 DOC-R08.1 – R08.3, S-04).
 */
final readonly class GateDocumentPermissions implements DocumentPermissions
{
    private const string ISSUE_DOCUMENTS = 'emitir-documentos';

    private const string MANAGE_STUDENTS = 'gestionar-estudiantes';

    public function __construct(
        private Gate $gate,
        private Auth $auth,
    ) {}

    public function canIssue(): bool
    {
        return $this->gate->allows(self::ISSUE_DOCUMENTS);
    }

    public function hasFullScope(): bool
    {
        if (! $this->canIssue()) {
            return false;
        }

        // Administrador and Director carry gestionar-estudiantes -> full scope.
        if ($this->gate->allows(self::MANAGE_STUDENTS)) {
            return true;
        }

        $user = $this->auth->guard()->user();

        if ($user === null || ! method_exists($user, 'hasRole')) {
            return false;
        }

        // A Coordinator (without Administrador/Director) is restricted to its
        // coordinated grades (DOC-R08.3). A non-Coordinator member granted
        // emitir-documentos individually (e.g. a secretary) has full scope.
        return ! $user->hasRole('Coordinador');
    }

    public function currentAccountId(): ?int
    {
        $id = $this->auth->guard()->id();

        return $id === null ? null : (int) $id;
    }
}
