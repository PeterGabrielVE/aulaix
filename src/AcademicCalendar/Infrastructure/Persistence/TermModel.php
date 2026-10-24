<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence of a term of a school year.
 *
 * @property int $id
 * @property int $school_year_id
 * @property int $number
 * @property string $starts_on
 * @property string $ends_on
 * @property string $status
 */
final class TermModel extends Model
{
    protected $table = 'terms';

    protected $guarded = ['id', 'institution_id'];
}
