<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant isolation for child rows: only rows whose parent relation (named by tenantParent()) is visible.
 * The parent's own scope applies inside whereHas, so this chains up to the company.
 */
trait ScopedThrough
{
    /** Name of the relation that leads towards the company-scoped parent. */
    abstract public static function tenantParent(): string;

    protected static function bootScopedThrough(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if (auth()->user()) {
                $query->whereHas(static::tenantParent());
            }
        });
    }
}
