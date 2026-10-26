<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorPasswords;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Application\Port\SecurityLog;
use AulaX\Platform\Application\Port\TotpCodes;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * An operator enters the panel with email, password and code at once
 * (PLT-R01.2): a TOTP, or one of its recovery codes (PLT-R01.5).
 *
 * Every failure gives the same answer and the same audit entry, and does
 * the same work: a password is compared and a code checked even when the
 * email is unknown or the password wrong (PLT-R01.3, V5). Why it failed
 * only reaches the application log.
 */
final readonly class LogInOperatorHandler
{
    /**
     * Checked when the email has no operator, so it costs the same.
     */
    private const string DUMMY_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public function __construct(
        private OperatorRepository $operators,
        private OperatorPasswords $passwords,
        private TotpCodes $totp,
        private OperatorSession $session,
        private SecurityLog $log,
        private AuditContext $auditContext,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return ?int Recovery codes left, when one was used.
     *
     * @throws BusinessRuleViolation «Datos de acceso incorrectos.», whatever failed.
     */
    public function __invoke(string $email, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $code): ?int
    {
        $now = $this->clock->now();
        $operator = $this->operatorWith($email);

        $passwordMatches = $this->passwords->check($password, $operator?->passwordHash);
        $step = $this->totp->verify($operator?->totpSecret ?? self::DUMMY_SECRET, $code, $now);
        $codes = $operator?->id === null ? null : $this->operators->recoveryCodes($operator->id);

        $reason = match (true) {
            $operator === null => 'unknown_email',
            ! $passwordMatches => 'wrong_password',
            ! $operator->canLogIn() => 'not_active',
            $step !== null && $operator->totpLastStep !== null && $step <= $operator->totpLastStep => 'code_reused',
            $step === null && ! $codes?->accepts($code) => 'wrong_code',
            default => null,
        };

        if ($reason !== null) {
            $this->fail($operator, $reason);
        }

        return $this->transactions->run(function () use ($operator, $step, $codes, $code, $now) {
            $left = null;

            if ($step !== null) {
                $operator->useTotpStep($step);
            } else {
                $codes = $codes->use($code);
                $this->operators->saveRecoveryCodes($operator->id, $codes);
                $left = $codes->remaining();
            }

            $this->operators->save($operator);
            $this->session->start($operator, $now);
            $this->auditContext->actAsPlatformOperator($operator->id->value);
            $this->audit->recordForPlatform(PlatformAudit::loginSucceeded($operator->id));

            return $left;
        });
    }

    private function operatorWith(string $email): ?Operator
    {
        try {
            return $this->operators->byEmail(OperatorEmail::fromString($email));
        } catch (BusinessRuleViolation) {
            return null;
        }
    }

    /**
     * @throws BusinessRuleViolation
     */
    private function fail(?Operator $operator, string $reason): never
    {
        $this->log->loginFailed($operator?->id?->value, $reason);
        $this->transactions->run(fn () => $this->audit->recordForPlatform(PlatformAudit::loginFailed()));

        throw BusinessRuleViolation::onField('email', 'Datos de acceso incorrectos.');
    }
}
