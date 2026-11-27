<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Persistence;

use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentOperatorInvitationRepository implements OperatorInvitationRepository
{
    private const string TABLE = 'platform_operator_invitations';

    public function issue(OperatorInvitation $invitation): int
    {
        return DB::transaction(function () use ($invitation) {
            $this->revokeAllOf($invitation->operatorId);

            return (int) DB::table(self::TABLE)->insertGetId([
                'operator_id' => $invitation->operatorId->value,
                'purpose' => $invitation->purpose->value,
                'token_hash' => $invitation->tokenHash,
                'expires_at' => $invitation->expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function byTokenHash(string $tokenHash): ?OperatorInvitation
    {
        $row = DB::table(self::TABLE)->where('token_hash', $tokenHash)->first();

        return $row === null ? null : OperatorInvitation::reconstitute(
            (int) $row->id,
            OperatorId::fromInt((int) $row->operator_id),
            InvitationPurpose::from($row->purpose),
            $row->token_hash,
            new DateTimeImmutable($row->expires_at),
            $row->used_at === null ? null : new DateTimeImmutable($row->used_at),
            (bool) $row->revoked,
        );
    }

    public function consume(OperatorInvitation $invitation, DateTimeImmutable $now): bool
    {
        return DB::table(self::TABLE)
            ->where('id', $invitation->id)
            ->whereNull('used_at')
            ->where('revoked', false)
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now, 'updated_at' => now()]) === 1;
    }

    public function revokeAllOf(OperatorId $operator): void
    {
        DB::table(self::TABLE)
            ->where('operator_id', $operator->value)
            ->whereNull('used_at')
            ->where('revoked', false)
            ->update(['revoked' => true, 'updated_at' => now()]);
    }
}
