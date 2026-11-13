<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * All businesses share one database. A model with this trait only ever shows the rows
 * of the signed-in user's business, and new rows are stamped with that business.
 * Where nobody is signed in (console commands, the admin panel) nothing is hidden,
 * so that code must say which business it means.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if ($id = tenant_id()) {
                $query->where($query->getModel()->getTable().'.tenant_id', $id);
            }
        });

        static::creating(function ($model) {
            $model->tenant_id ??= tenant_id();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
