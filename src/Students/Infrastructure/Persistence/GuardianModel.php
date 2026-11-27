<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * A guardian of the open institution. Row Level Security limits it to the
 * open institution, whose id the column default takes.
 *
 * @property int $id
 * @property string $given_names
 * @property string $family_names
 * @property string $document_type
 * @property string $document
 * @property string $phone
 * @property ?string $email
 * @property ?string $address
 * @property ?int $account_id
 * @property ?int $pending_invitation_id
 */
final class GuardianModel extends Model
{
    protected $table = 'guardians';

    protected $guarded = ['id', 'institution_id'];
}
