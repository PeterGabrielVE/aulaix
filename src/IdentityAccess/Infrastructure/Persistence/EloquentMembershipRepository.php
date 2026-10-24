<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\Membership;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Membership\SuspensionReason;
use AulaX\Shared\Domain\InstitutionId;
use Illuminate\Support\Facades\Date;

final class EloquentMembershipRepository implements MembershipRepository
{
    public function of(AccountId $accountId, InstitutionId $institutionId): ?Membership
    {
        $model = MembershipModel::query()
            ->where('institution_id', $institutionId->value)
            ->where('user_id', $accountId->value)
            ->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function allOf(AccountId $accountId): array
    {
        return MembershipModel::query()
            ->where('user_id', $accountId->value)
            ->orderBy('id')
            ->get()
            ->map(fn (MembershipModel $model) => self::toDomain($model))
            ->values()
            ->all();
    }

    public function save(Membership $membership): Membership
    {
        $model = $membership->id === null ? new MembershipModel : MembershipModel::query()->findOrFail($membership->id);

        $model->forceFill([
            'institution_id' => $membership->institutionId->value,
            'user_id' => $membership->accountId->value,
            'status' => $membership->status->value,
            'suspension_reason' => $membership->suspensionReason?->value,
            'ended_at' => $membership->status === MembershipStatus::Ended ? ($model->ended_at ?? Date::now()->toDateTimeImmutable()) : null,
        ])->save();

        return self::toDomain($model);
    }

    private static function toDomain(MembershipModel $model): Membership
    {
        return Membership::reconstitute(
            (int) $model->id,
            AccountId::fromInt((int) $model->user_id),
            InstitutionId::fromInt((int) $model->institution_id),
            MembershipStatus::from($model->status),
            $model->suspension_reason === null ? null : SuspensionReason::from($model->suspension_reason),
        );
    }
}
