<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\IdentityAccess\Domain\Invitation\Invitation;
use AulaX\IdentityAccess\Domain\Invitation\InvitationRepository;
use AulaX\IdentityAccess\Domain\Invitation\InvitationStatus;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Domain\InstitutionId;

final class EloquentInvitationRepository implements InvitationRepository
{
    public function byId(InstitutionId $institutionId, int $id): ?Invitation
    {
        $model = InvitationModel::query()->where('institution_id', $institutionId->value)->find($id);

        return $model === null ? null : self::toDomain($model);
    }

    public function pendingFor(InstitutionId $institutionId, EmailAddress $email): ?Invitation
    {
        $model = InvitationModel::query()
            ->where('institution_id', $institutionId->value)
            ->where('email', $email->value)
            ->where('status', InvitationStatus::Pending->value)
            ->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function save(Invitation $invitation): Invitation
    {
        $model = $invitation->id === null ? new InvitationModel : InvitationModel::query()->findOrFail($invitation->id);

        $model->forceFill([
            'institution_id' => $invitation->institutionId->value,
            'email' => $invitation->email->value,
            'name' => $invitation->name->value,
            'roles' => $invitation->roles->inPriorityOrder(),
            'token_hash' => $invitation->tokenHash,
            'expires_at' => $invitation->expiresAt,
            'status' => $invitation->status->value,
            'invited_by' => $invitation->invitedBy?->value,
            'accepted_by' => $invitation->acceptedBy?->value,
        ])->save();

        return self::toDomain($model);
    }

    private static function toDomain(InvitationModel $model): Invitation
    {
        return Invitation::reconstitute(
            (int) $model->id,
            InstitutionId::fromInt((int) $model->institution_id),
            EmailAddress::fromString($model->email),
            PersonName::fromString($model->name),
            RoleSet::of(...$model->roles),
            $model->token_hash,
            $model->expires_at,
            InvitationStatus::from($model->status),
            $model->invited_by === null ? null : AccountId::fromInt((int) $model->invited_by),
            $model->accepted_by === null ? null : AccountId::fromInt((int) $model->accepted_by),
        );
    }
}
