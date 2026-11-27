<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persistence of a school year. Row Level Security limits it to the active
 * institution, whose id the column default takes.
 *
 * @property int $id
 * @property string $starts_on
 * @property string $ends_on
 * @property string $status
 */
final class SchoolYearModel extends Model
{
    protected $table = 'school_years';

    protected $guarded = ['id', 'institution_id'];

    /**
     * @return HasMany<TermModel, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(TermModel::class, 'school_year_id')->orderBy('number');
    }
}
