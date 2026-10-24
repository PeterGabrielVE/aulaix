<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * A signature of the active institution. Row Level Security limits it to the
 * active institution, whose id the column default takes.
 *
 * @property string $role
 * @property string $signer_name
 * @property string $signer_title
 * @property string $file_path
 */
final class SignatureModel extends Model
{
    protected $table = 'institution_signatures';

    protected $guarded = ['id', 'institution_id'];
}
