<?php

declare(strict_types=1);

namespace AulaX\Platform\Application;

use AulaX\Platform\Application\Access\RecentTotpQuery;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\Clock;
use DateTimeImmutable;

/**
 * The use cases' own check of who acts (S-04, PLT-NF05, SPEC-017 RSP-NF03),
 * on top of the routes' middleware: an active, unblocked operator of the
 * session, a SuperAdmin for management, and a recent TOTP for sensitive actions.
 */
final class PlatformAccess
{
    /**
     * @throws AuthorizationDenied
     */
    public static function operator(OperatorSession $session, OperatorRepository $operators, ?DateTimeImmutable $now = null): Operator
    {
        $id = $session->current();
        $operator = $id === null ? null : $operators->byId($id);

        if ($operator === null || ! $operator->canLogIn() || ($now !== null && $operator->isBlockedAt($now))) {
            throw new AuthorizationDenied('Necesitas una sesión de operador.');
        }

        return $operator;
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function superAdmin(OperatorSession $session, OperatorRepository $operators, ?DateTimeImmutable $now = null): Operator
    {
        $operator = self::operator($session, $operators, $now);

        if (! $operator->role->canManage()) {
            throw new AuthorizationDenied('Solo un Superadministrador puede hacer esto.');
        }

        return $operator;
    }

    /**
     * SPEC-017 RSP-NF03: verifies a TOTP confirmed within the last 10 minutes
     * inside the use case itself, not only in the HTTP middleware.
     *
     * @throws AuthorizationDenied
     */
    public static function withRecentTotp(OperatorSession $session, OperatorRepository $operators, Clock $clock): Operator
    {
        $now = $clock->now();
        $operator = self::operator($session, $operators, $now);
        self::assertRecentTotp($session, $now);

        return $operator;
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function superAdminWithRecentTotp(OperatorSession $session, OperatorRepository $operators, Clock $clock): Operator
    {
        $now = $clock->now();
        $operator = self::superAdmin($session, $operators, $now);
        self::assertRecentTotp($session, $now);

        return $operator;
    }

    /**
     * @throws AuthorizationDenied
     */
    private static function assertRecentTotp(OperatorSession $session, DateTimeImmutable $now): void
    {
        $confirmedAt = $session->totpConfirmedAt();

        if ($confirmedAt === null || $now->getTimestamp() - $confirmedAt->getTimestamp() > RecentTotpQuery::WINDOW_SECONDS) {
            throw new AuthorizationDenied('Confirma tu código antes de hacer esto.');
        }
    }
}
