<?php

declare(strict_types=1);

namespace AulaX\Platform\Application;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\FieldChange;
use AulaX\Platform\Domain\OperatorId;

/**
 * What the platform log keeps of the panel (PLT-R07.1, R07.3): who acted,
 * on what, and what changed. Never passwords, TOTP secrets, recovery codes
 * nor tokens.
 */
final class PlatformAudit
{
    public static function loginSucceeded(OperatorId $operator): AuditEvent
    {
        return AuditEvent::of('platform.login.succeeded', self::operator($operator));
    }

    /**
     * The same for every failure (V5): no email typed, no cause.
     */
    public static function loginFailed(): AuditEvent
    {
        return AuditEvent::of('platform.login.failed', AuditSubject::of('platform_login', 'panel'));
    }

    public static function logout(OperatorId $operator): AuditEvent
    {
        return AuditEvent::of('platform.session.ended', self::operator($operator));
    }

    /**
     * V3: who was invited, with which role. The email is an operator's
     * work address, kept so the log says who was let in.
     */
    public static function invited(OperatorId $operator, string $email, string $role): AuditEvent
    {
        return AuditEvent::of('platform.operator.invited', self::operator($operator), [
            FieldChange::of('email', null, $email),
            FieldChange::of('role', null, $role),
        ]);
    }

    public static function activated(OperatorId $operator): AuditEvent
    {
        return AuditEvent::of('platform.operator.activated', self::operator($operator), [FieldChange::of('status', 'pending', 'active')]);
    }

    public static function totpEnrolled(OperatorId $operator): AuditEvent
    {
        return AuditEvent::of('platform.operator.totp_enrolled', self::operator($operator));
    }

    public static function roleChanged(OperatorId $operator, string $from, string $to): AuditEvent
    {
        return AuditEvent::of('platform.operator.role_changed', self::operator($operator), [FieldChange::of('role', $from, $to)]);
    }

    public static function activeChanged(OperatorId $operator, bool $active, string $from, string $to): AuditEvent
    {
        return AuditEvent::of(
            $active ? 'platform.operator.reactivated' : 'platform.operator.deactivated',
            self::operator($operator),
            [FieldChange::of('status', $from, $to)],
        );
    }

    public static function mfaReset(OperatorId $operator): AuditEvent
    {
        return AuditEvent::of('platform.operator.mfa_reset', self::operator($operator));
    }

    public static function recoveryCodesRegenerated(OperatorId $operator): AuditEvent
    {
        return AuditEvent::of('platform.operator.recovery_codes_regenerated', self::operator($operator));
    }

    public static function institutionViewed(int $institutionId): AuditEvent
    {
        return AuditEvent::of('platform.institution.viewed', AuditSubject::of('institution', $institutionId));
    }

    public static function institutionStatusChanged(int $institutionId, bool $active, string $reason): AuditEvent
    {
        return AuditEvent::of(
            $active ? 'platform.institution.reactivated' : 'platform.institution.deactivated',
            AuditSubject::of('institution', $institutionId),
            [
                FieldChange::of('status', $active ? 'inactive' : 'active', $active ? 'active' : 'inactive'),
                FieldChange::of('reason', null, $reason),
            ],
        );
    }

    public static function operator(OperatorId $operator): AuditSubject
    {
        return AuditSubject::of('platform_operator', $operator->value);
    }
}
