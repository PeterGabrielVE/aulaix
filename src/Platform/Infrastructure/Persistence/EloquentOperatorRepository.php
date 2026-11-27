<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Persistence;

use AulaX\Platform\Domain\CodeFailure;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Infrastructure\AdvisoryLockKeys;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The tables are only visible with no institution active (ADR 0010).
 * Updates to existing operators and recovery codes require the transaction's
 * advisory lock on that operator (ADR 0014, rule 3).
 */
final class EloquentOperatorRepository implements OperatorRepository
{
    public function byId(OperatorId $id): ?Operator
    {
        $model = OperatorModel::query()->find($id->value);

        return $model === null ? null : self::toDomain($model);
    }

    public function byEmail(OperatorEmail $email): ?Operator
    {
        $model = OperatorModel::query()->where('email', $email->value)->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function all(): array
    {
        return OperatorModel::query()->orderBy('id')->get()->map(self::toDomain(...))->values()->all();
    }

    public function save(Operator $operator): OperatorId
    {
        if ($operator->id !== null) {
            $this->assertOperatorLocked($operator->id);
        }

        $model = $operator->id === null ? new OperatorModel : OperatorModel::query()->findOrFail($operator->id->value);

        $model->fill([
            'name' => $operator->name,
            'email' => $operator->email->value,
            'password' => $operator->passwordHash,
            'role' => $operator->role->value,
            'status' => $operator->status->value,
            'totp_secret' => $operator->totpSecret,
            'totp_last_step' => $operator->totpLastStep,
            'session_version' => $operator->sessionVersion,
            'code_failures' => array_map(
                fn (CodeFailure $failure) => ['at' => $failure->occurredAt->format(DATE_ATOM), 'ip' => $failure->ip],
                $operator->codeFailures,
            ),
            'blocked_until' => $operator->blockedUntil,
        ])->save();

        return OperatorId::fromInt((int) $model->id);
    }

    public function recoveryCodes(OperatorId $id): RecoveryCodes
    {
        return RecoveryCodes::reconstitute(
            DB::table('platform_operator_recovery_codes')
                ->where('operator_id', $id->value)
                ->orderBy('id')
                ->get(['code_hash', 'used_at'])
                ->map(fn (object $row) => ['hash' => $row->code_hash, 'used' => $row->used_at !== null])
                ->values()
                ->all(),
        );
    }

    public function saveRecoveryCodes(OperatorId $id, RecoveryCodes $codes): void
    {
        $this->assertOperatorLocked($id);

        DB::transaction(function () use ($id, $codes) {
            DB::table('platform_operator_recovery_codes')->where('operator_id', $id->value)->delete();
            DB::table('platform_operator_recovery_codes')->insert(array_map(fn (array $code) => [
                'operator_id' => $id->value,
                'code_hash' => $code['hash'],
                'used_at' => $code['used'] ? now() : null,
            ], $codes->hashes()));
        });
    }

    /**
     * ADR 0014 rule 3: fail fast if a use case forgets OperatorLock::lockOperator().
     */
    private function assertOperatorLocked(OperatorId $id): void
    {
        $held = DB::selectOne(
            "SELECT 1 FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid() AND granted = true AND classid::bigint = ? AND objid::bigint = ? AND objsubid = 2",
            [AdvisoryLockKeys::OPERATOR, $id->value],
        );

        if ($held === null) {
            throw new LogicException("Operator {$id->value} must be locked with OperatorLock before saving.");
        }
    }

    private static function toDomain(OperatorModel $model): Operator
    {
        $failures = array_map(
            fn (array $row) => new CodeFailure(new DateTimeImmutable($row['at']), (string) $row['ip']),
            is_array($model->code_failures) ? $model->code_failures : [],
        );

        return Operator::reconstitute(
            OperatorId::fromInt($model->id),
            $model->name,
            OperatorEmail::fromString($model->email),
            OperatorRole::from($model->role),
            OperatorStatus::from($model->status),
            $model->password,
            $model->totp_secret,
            $model->totp_last_step,
            $model->session_version,
            $failures,
            $model->blocked_until === null ? null : DateTimeImmutable::createFromInterface($model->blocked_until),
        );
    }
}
