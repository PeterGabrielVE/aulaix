<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $fillable = ['state_id', 'name', 'code'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function parishes(): HasMany
    {
        return $this->hasMany(Parish::class);
    }
}
