<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * A student of the open institution. Row Level Security limits it to the
 * open institution, whose id the column default takes.
 *
 * @property int $id
 * @property string $given_names
 * @property string $family_names
 * @property string $sex
 * @property string $birth_date
 * @property string $nationality
 * @property string $identification_type
 * @property string $identification
 * @property ?string $birth_country
 * @property ?int $birth_state_id
 * @property ?int $parish_id
 * @property ?string $address
 * @property ?string $emergency_phone
 * @property ?string $allergies
 * @property ?string $medical_conditions
 * @property ?string $disability
 * @property string $status
 */
final class StudentModel extends Model
{
    protected $table = 'students';

    protected $guarded = ['id', 'institution_id'];
}
