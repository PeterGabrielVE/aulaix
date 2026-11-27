<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use AulaX\Catalogs\Domain\EducationLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * The table is explicit: see StateModel.
 */
final class SubjectModel extends Model
{
    protected $table = 'subjects';

    protected $fillable = ['name', 'code', 'education_level'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'education_level' => EducationLevel::class,
        ];
    }
}
