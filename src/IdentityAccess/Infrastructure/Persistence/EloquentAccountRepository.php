<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\AccountState;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\PersonName;

/**
 * Accounts as Row Level Security shows them (ADR 0006): with an institution
 * active, only its members; with none, every account.
 */
final class EloquentAccountRepository implements AccountRepository
{
    public function byId(AccountId $id): ?Account
    {
        $model = AccountModel::query()->find($id->value);

        return $model === null ? null : self::toDomain($model);
    }

    public function byEmail(EmailAddress $email): array
    {
        return AccountModel::query()
            ->where('email', $email->value)
            ->orderBy('id')
            ->get()
            ->map(fn (AccountModel $model) => self::toDomain($model))
            ->values()
            ->all();
    }

    public function save(Account $account): Account
    {
        $model = $account->id === null ? new AccountModel : AccountModel::query()->findOrFail($account->id->value);

        $model->forceFill([
            'name' => $account->name->value,
            'email' => $account->email->value,
            'account_state' => $account->state->value,
            'consolidation_state' => $account->consolidation?->value,
            'email_verified_at' => $account->emailVerifiedAt,
        ]);

        if ($account->newPassword !== null) {
            $model->forceFill(['password' => $account->newPassword->value()]);
        }

        $model->save();

        return self::toDomain($model);
    }

    public function delete(AccountId $id): void
    {
        AccountModel::query()->whereKey($id->value)->delete();
    }

    private static function toDomain(AccountModel $model): Account
    {
        return Account::reconstitute(
            AccountId::fromInt((int) $model->id),
            EmailAddress::fromString($model->email),
            PersonName::fromString($model->name),
            AccountState::from($model->account_state ?? AccountState::Active->value),
            $model->consolidation_state === null ? null : ConsolidationState::from($model->consolidation_state),
            $model->email_verified_at?->toDateTimeImmutable(),
        );
    }
}
