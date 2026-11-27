<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence of an invitation. Row Level Security limits it to the active
 * institution.
 *
 * @property int $id
 * @property int $institution_id
 * @property string $email
 * @property string $name
 * @property list<string> $roles
 * @property string $token_hash
 * @property DateTimeImmutable $expires_at
 * @property string $status
 * @property ?int $invited_by
 * @property ?int $accepted_by
 */
final class InvitationModel extends Model
{
    protected $table = 'invitations';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
