<?php

namespace App\Models;

use App\Enums\EducationLevel;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
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
