<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Table and foreign keys are explicit: see StateModel.
 */
final class MunicipalityModel extends Model
{
    protected $table = 'municipalities';

    protected $fillable = ['state_id', 'name', 'code'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(StateModel::class, 'state_id');
    }

    public function parishes(): HasMany
    {
        return $this->hasMany(ParishModel::class, 'municipality_id');
    }
}
