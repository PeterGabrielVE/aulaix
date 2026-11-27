<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence of a membership. Row Level Security limits it to the active
 * institution.
 *
 * @property int $id
 * @property int $institution_id
 * @property int $user_id
 * @property string $status
 * @property ?string $suspension_reason
 */
final class MembershipModel extends Model
{
    protected $table = 'institution_memberships';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ended_at' => 'immutable_datetime',
        ];
    }
}
