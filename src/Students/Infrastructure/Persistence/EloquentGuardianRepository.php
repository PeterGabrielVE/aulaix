<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use AulaX\Shared\Domain\Conflict;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Identity\GuardianDocument;
use Illuminate\Database\QueryException;

final class EloquentGuardianRepository implements GuardianRepository
{
    private const string UNIQUE_VIOLATION = '23505';

    public function byId(GuardianId $id): ?Guardian
    {
        $model = GuardianModel::query()->find($id->value);

        return $model === null ? null : self::toDomain($model);
    }

    public function byDocument(GuardianDocument $document): ?Guardian
    {
        $model = GuardianModel::query()
            ->where('document_type', $document->type)
            ->where('document', $document->value())
            ->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function save(Guardian $guardian): GuardianId
    {
        $data = $guardian->data();
        $attributes = [
            'given_names' => $data->givenNames,
            'family_names' => $data->familyNames,
            'document_type' => $data->document->type,
            'document' => $data->document->value(),
            'phone' => $data->phone,
            'email' => $data->email,
            'address' => $data->address,
            'account_id' => $guardian->accountId(),
            'pending_invitation_id' => $guardian->pendingInvitationId(),
        ];

        try {
            $model = $guardian->id() === null
                ? GuardianModel::query()->create($attributes)
                : tap(GuardianModel::query()->findOrFail($guardian->id()->value))->update($attributes);
        } catch (QueryException $exception) {
            if ($exception->getCode() === self::UNIQUE_VIOLATION) {
                throw new Conflict('Ya hay un representante con ese documento en el plantel.', previous: $exception);
            }

            throw $exception;
        }

        return GuardianId::fromInt((int) $model->id);
    }

    public static function toDomain(GuardianModel $model): Guardian
    {
        return Guardian::reconstitute(
            GuardianId::fromInt((int) $model->id),
            GuardianData::reconstitute(
                $model->given_names,
                $model->family_names,
                GuardianDocument::fromInput($model->document_type, $model->document),
                $model->phone,
                $model->email,
                $model->address,
            ),
            $model->account_id === null ? null : (int) $model->account_id,
            $model->pending_invitation_id === null ? null : (int) $model->pending_invitation_id,
        );
    }
}
