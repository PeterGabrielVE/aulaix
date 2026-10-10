<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Table and foreign keys are explicit: Eloquent would otherwise infer them
 * from the class name ("state_models", "state_model_id").
 */
final class StateModel extends Model
{
    protected $table = 'states';

    protected $fillable = ['name', 'code'];

    public function municipalities(): HasMany
    {
        return $this->hasMany(MunicipalityModel::class, 'state_id');
    }
}
