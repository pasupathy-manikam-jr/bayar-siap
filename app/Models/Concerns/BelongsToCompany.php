<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation: when a contractor user is signed in, every query is limited to their company
 * and new rows get their company_id. Client-link code has no user and scopes through the job instead.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if ($user = auth()->user()) {
                $query->where($query->qualifyColumn('company_id'), $user->company_id);
            }
        });

        static::creating(function ($model) {
            if (! $model->company_id && $user = auth()->user()) {
                $model->company_id = $user->company_id;
            }
        });
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
