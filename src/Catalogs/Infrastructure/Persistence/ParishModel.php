<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table and foreign keys are explicit: see StateModel.
 */
final class ParishModel extends Model
{
    protected $table = 'parishes';

    protected $fillable = ['municipality_id', 'name', 'code'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(MunicipalityModel::class, 'municipality_id');
    }
}
