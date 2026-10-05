<?php

namespace App\Concerns;

use App\Models\Institution;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Application-level convenience scope for tenant-scoped models: filters
 * queries to the current institution and auto-fills institution_id on
 * create. This is NOT the security boundary — Postgres Row Level Security
 * (see the enable-RLS migration) is, and stays enforced even if this scope
 * is bypassed with ->withoutGlobalScope() or raw SQL.
 */
trait BelongsToInstitution
{
    public static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $builder) {
            if ($tenantId = app(CurrentTenant::class)->id()) {
                $builder->where($builder->getModel()->getTable().'.institution_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            if (! $model->institution_id && $tenantId = app(CurrentTenant::class)->id()) {
                $model->institution_id = $tenantId;
            }
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
