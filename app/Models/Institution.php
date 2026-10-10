<?php

namespace App\Models;

use App\Enums\InstitutionStatus;
use AulaX\Catalogs\Infrastructure\Persistence\ParishModel;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    /**
     * Subdomains of APP_DOMAIN that belong to the platform itself, never to
     * an institution. Enforced by the institutions_subdomain_format check
     * constraint; "www" is also redirected to the central domain.
     */
    public const RESERVED_SUBDOMAINS = ['www', 'api', 'admin', 'app', 'mail', 'static', 'assets', 'cdn'];

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
     * Hostnames are case-insensitive and the request's host always arrives
     * lower-cased, so the stored subdomain must be lower-case to match it.
     */
    protected function subdomain(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower(trim($value)),
        );
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
        return $this->belongsTo(ParishModel::class);
    }
}
