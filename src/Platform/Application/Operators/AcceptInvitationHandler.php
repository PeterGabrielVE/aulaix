<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Operators;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorPasswords;
use AulaX\Platform\Application\Port\PendingTotpSecrets;
use AulaX\Platform\Application\Port\TotpCodes;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorInvitationRejected;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * Accepting a link (PLT-R01.6, R01.10, R03.1, R03.7): a first code confirms
 * the TOTP shown, the joining operator also sets its password, and ten
 * recovery codes are handed over once. The link is consumed atomically
 * with the change: of two racing requests, one wins (V4).
 */
final readonly class AcceptInvitationHandler
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorInvitationRepository $invitations,
        private OperatorPasswords $passwords,
        private TotpCodes $totp,
        private PendingTotpSecrets $secrets,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  ?string  $password  Only when joining; ignored when enrolling again.
     * @return list<string> The recovery codes, shown once and never kept in clear.
     *
     * @throws OperatorInvitationRejected
     * @throws BusinessRuleViolation
     */
    public function __invoke(#[\SensitiveParameter] string $token, #[\SensitiveParameter] ?string $password, #[\SensitiveParameter] string $code): array
    {
        $now = $this->clock->now();
        $hash = hash('sha256', $token);
        $invitation = $this->invitations->byTokenHash($hash);
        $operator = $invitation === null ? null : $this->operators->byId($invitation->operatorId);
        $secret = $this->secrets->get($hash);

        if ($invitation === null || $operator === null || $secret === null || ! $invitation->isUsableAt($now)) {
            throw OperatorInvitationRejected::create();
        }

        $joining = $invitation->purpose === InvitationPurpose::Onboarding;

        if ($joining && ($password === null || $password === '')) {
            throw BusinessRuleViolation::onField('password', 'Elige una contraseña.');
        }

        $step = $this->totp->verify($secret, $code, $now)
            ?? throw BusinessRuleViolation::onField('code', 'El código no es correcto.');

        $plain = self::newRecoveryCodes();

        $this->transactions->run(function () use ($invitation, $operator, $joining, $password, $secret, $step, $plain, $now) {
            if (! $this->invitations->consume($invitation, $now)) {
                throw OperatorInvitationRejected::create();
            }

            if ($joining) {
                $operator->activate($this->passwords->hash((string) $password), $secret, $step);
            } else {
                $operator->enrolTotp($secret, $step);
            }

            $this->operators->save($operator);
            $this->operators->saveRecoveryCodes($operator->id, RecoveryCodes::fromPlain($plain));
            $this->auditContext->actAsPlatformOperator($operator->id->value);
            $this->audit->recordForPlatform($joining ? PlatformAudit::activated($operator->id) : PlatformAudit::totpEnrolled($operator->id));
        });

        $this->secrets->forget($hash);

        return $plain;
    }

    /**
     * @return list<string> Ten codes like «k3f9q-2x7mz».
     */
    public static function newRecoveryCodes(): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $codes = [];

        while (count($codes) < RecoveryCodes::COUNT) {
            $code = '';

            for ($i = 0; $i < 10; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $codes[substr($code, 0, 5).'-'.substr($code, 5)] = true;
        }

        return array_keys($codes);
    }
}
