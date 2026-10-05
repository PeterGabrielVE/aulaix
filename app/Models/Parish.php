<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parish extends Model
{
    protected $fillable = ['municipality_id', 'name', 'code'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
