<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Access;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\PlatformAudit;
use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Application\Port\TotpCodes;
use AulaX\Platform\Domain\CodeFailure;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\RecoveryCodeHasher;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Clock;
use DateTimeImmutable;

/**
 * Shared locked verification sequence for panel login, TOTP confirmation and
 * console authentication (SPEC-017 RSP-R01, R03, R06, ADR 0014).
 *
 * Inside a single transaction: acquires the operator advisory lock, re-reads
 * the operator, checks status and lockout, verifies the code, and either
 * consumes it or records the consecutive failure (locking on the 10th). Any
 * lockout notification email is sent only after the transaction commits.
 */
final readonly class CodeAttempt
{
    public function __construct(
        private OperatorRepository $operators,
        private OperatorLock $locks,
        private TotpCodes $totp,
        private RecoveryCodeHasher $recoveryHasher,
        private OperatorMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @param  ?callable(Operator, ?int, DateTimeImmutable): void  $onSuccess
     */
    public function attempt(
        OperatorId $operatorId,
        #[\SensitiveParameter] string $code,
        string $ip,
        bool $allowRecoveryCode,
        ?callable $onSuccess = null,
    ): CodeAttemptResult {
        $now = $this->clock->now();
        /** @var ?array{operator: Operator, failures: list<CodeFailure>, superAdmins: list<OperatorEmail>} $blockedNotice */
        $blockedNotice = null;

        /** @var CodeAttemptResult $result */
        $result = $this->transactions->run(function () use ($operatorId, $code, $ip, $allowRecoveryCode, $onSuccess, $now, &$blockedNotice) {
            $this->locks->lockOperator($operatorId);
            $operator = $this->operators->byId($operatorId);

            if ($operator === null || ! $operator->canLogIn()) {
                return CodeAttemptResult::rejected('not_active', blockedNow: false);
            }

            if ($operator->isBlockedAt($now)) {
                return CodeAttemptResult::rejected('blocked', blockedNow: false);
            }

            $step = $this->totp->verify((string) $operator->totpSecret, $code, $now);

            if ($step !== null) {
                if ($operator->totpLastStep !== null && $step <= $operator->totpLastStep) {
                    return $this->recordFailure($operator, 'code_reused', $now, $ip, $blockedNotice);
                }

                $operator->useTotpStep($step);
                $this->operators->save($operator);

                if ($onSuccess !== null) {
                    $onSuccess($operator, null, $now);
                }

                return CodeAttemptResult::accepted($operator, recoveryCodesLeft: null);
            }

            if ($allowRecoveryCode) {
                $codes = $this->operators->recoveryCodes($operator->id);

                if ($codes->accepts($code, $this->recoveryHasher)) {
                    $updated = $codes->use($code, $this->recoveryHasher);
                    $operator->recordRecoveryCodeUsed();
                    $this->operators->saveRecoveryCodes($operator->id, $updated);
                    $this->operators->save($operator);
                    $left = $updated->remaining();

                    if ($onSuccess !== null) {
                        $onSuccess($operator, $left, $now);
                    }

                    return CodeAttemptResult::accepted($operator, recoveryCodesLeft: $left);
                }
            }

            return $this->recordFailure($operator, 'wrong_code', $now, $ip, $blockedNotice);
        });

        if ($blockedNotice !== null) {
            $this->mailer->operatorBlocked($blockedNotice['operator'], $blockedNotice['failures'], $blockedNotice['superAdmins']);
        }

        return $result;
    }

    /**
     * @param  ?array{operator: Operator, failures: list<CodeFailure>, superAdmins: list<OperatorEmail>}  $blockedNotice
     */
    private function recordFailure(
        Operator $operator,
        string $reason,
        DateTimeImmutable $now,
        string $ip,
        ?array &$blockedNotice,
    ): CodeAttemptResult {
        $justBlocked = $operator->failCode($now, $ip);
        $this->operators->save($operator);

        if ($justBlocked) {
            $this->audit->recordForPlatform(PlatformAudit::operatorBlocked($operator->id));
            $blockedNotice = [
                'operator' => $operator,
                'failures' => $operator->codeFailures,
                'superAdmins' => $this->activeSuperAdminEmails(),
            ];
        }

        return CodeAttemptResult::rejected($reason, blockedNow: $justBlocked);
    }

    /**
     * @return list<OperatorEmail>
     */
    private function activeSuperAdminEmails(): array
    {
        return array_values(array_map(
            fn (Operator $operator) => $operator->email,
            array_filter(
                $this->operators->all(),
                fn (Operator $operator) => $operator->role === OperatorRole::SuperAdmin && $operator->status === OperatorStatus::Active,
            ),
        ));
    }
}
