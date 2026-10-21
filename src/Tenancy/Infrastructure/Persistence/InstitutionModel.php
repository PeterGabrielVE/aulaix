<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Catalogs\Infrastructure\Persistence\ParishModel;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\Subdomain;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistence of the Institution aggregate. The table is explicit: Eloquent
 * would otherwise infer "institution_models" from the class name.
 */
#[UseFactory(InstitutionFactory::class)]
final class InstitutionModel extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory;

    /**
     * @see Subdomain::RESERVED
     */
    public const RESERVED_SUBDOMAINS = Subdomain::RESERVED;

    protected $table = 'institutions';

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
        'type',
        'education_levels',
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
            'education_levels' => 'array',
        ];
    }

    /**
     * Normalized like the domain does, so writes that bypass it (seeders,
     * factories) store the same value. The format itself is enforced by the
     * institutions_subdomain_format check constraint.
     */
    protected function subdomain(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Subdomain::normalize($value),
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

    public function parish(): BelongsTo
    {
        return $this->belongsTo(ParishModel::class);
    }
}
