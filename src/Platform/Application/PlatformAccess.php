<?php

declare(strict_types=1);

namespace AulaX\Platform\Application;

use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The use cases' own check of who acts (S-04, PLT-NF05), on top of the
 * routes' middleware: an active operator of the session, and for managing,
 * a SuperAdmin.
 */
final class PlatformAccess
{
    /**
     * @throws AuthorizationDenied
     */
    public static function operator(OperatorSession $session, OperatorRepository $operators): Operator
    {
        $id = $session->current();
        $operator = $id === null ? null : $operators->byId($id);

        if ($operator === null || ! $operator->canLogIn()) {
            throw new AuthorizationDenied('Necesitas una sesión de operador.');
        }

        return $operator;
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function superAdmin(OperatorSession $session, OperatorRepository $operators): Operator
    {
        $operator = self::operator($session, $operators);

        if (! $operator->role->canManage()) {
            throw new AuthorizationDenied('Solo un Superadministrador puede hacer esto.');
        }

        return $operator;
    }
}
