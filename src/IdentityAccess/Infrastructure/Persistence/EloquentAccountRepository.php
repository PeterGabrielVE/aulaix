<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\AccountState;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\EmailChangeRequest;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use DateTimeImmutable;
use DateTimeInterface;

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
            'pending_email' => $account->pendingEmailChange?->email->value,
            'pending_email_token_hash' => $account->pendingEmailChange?->tokenHash,
            'pending_email_expires_at' => $account->pendingEmailChange?->expiresAt,
            'deletion_requested_at' => $account->deletionRequestedAt,
            'deletion_due_at' => $account->deletionDueAt,
        ]);

        if ($account->newPassword !== null) {
            $model->forceFill(['password' => $account->newPassword->value()]);
        }

        $model->save();

        return self::toDomain($model);
    }

    public function byPendingEmailToken(#[\SensitiveParameter] string $token): ?Account
    {
        $model = AccountModel::query()->where('pending_email_token_hash', EmailChangeRequest::hash($token))->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function dueForClosing(DateTimeImmutable $now): array
    {
        return AccountModel::query()
            ->where('account_state', AccountState::PendingDeletion->value)
            ->where('deletion_due_at', '<=', $now)
            ->orderBy('id')
            ->get()
            ->map(fn (AccountModel $model) => self::toDomain($model))
            ->values()
            ->all();
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
            $model->pending_email === null ? null : EmailChangeRequest::reconstitute(
                EmailAddress::fromString($model->pending_email),
                $model->pending_email_token_hash,
                self::date($model->pending_email_expires_at),
            ),
            self::date($model->deletion_requested_at),
            self::date($model->deletion_due_at),
        );
    }

    /**
     * As read from the database (a string), or as just saved (a date).
     */
    private static function date(mixed $value): ?DateTimeImmutable
    {
        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($value),
            default => new DateTimeImmutable((string) $value),
        };
    }
}
