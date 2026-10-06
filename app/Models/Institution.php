<?php

namespace App\Models;

use App\Enums\InstitutionStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'status',
        'dea_code',
        'rif',
        'parish_id',
        'address',
        'phone',
        'email',
        'logo_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InstitutionStatus::class,
        ];
    }

    /**
     * Only institutions that can be reached through their subdomain.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', InstitutionStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === InstitutionStatus::Active;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }
}
