<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Persistence;

use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\RecoveryCodes;
use Illuminate\Support\Facades\DB;

/**
 * The tables are only visible with no institution active (ADR 0010).
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
        DB::transaction(function () use ($id, $codes) {
            DB::table('platform_operator_recovery_codes')->where('operator_id', $id->value)->delete();
            DB::table('platform_operator_recovery_codes')->insert(array_map(fn (array $code) => [
                'operator_id' => $id->value,
                'code_hash' => $code['hash'],
                'used_at' => $code['used'] ? now() : null,
            ], $codes->hashes()));
        });
    }

    private static function toDomain(OperatorModel $model): Operator
    {
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
        );
    }
}
