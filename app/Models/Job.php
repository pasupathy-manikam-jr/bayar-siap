<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property string $title
 * @property string $site_address
 * @property string|null $lat
 * @property string|null $lng
 * @property int $quote_cents
 * @property string $status
 * @property Carbon|null $start_date
 * @property Carbon|null $target_date
 * @property Carbon|null $accepted_at
 * @property string $share_token
 * @property-read Client $client
 */
class Job extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft'];

    protected $hidden = ['share_token'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_date' => 'date',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Job $job) => $job->share_token ??= Str::random(48));
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Milestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sequence');
    }

    /** @return HasMany<VariationOrder, $this> */
    public function variationOrders(): HasMany
    {
        return $this->hasMany(VariationOrder::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activity(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest('id');
    }

    public function cancelReason(): ?string
    {
        if ($this->status !== 'cancelled') {
            return null;
        }
        $reason = $this->activity()->whereIn('action', ['job.cancelled', 'job.cancelled_draft'])->first()?->meta['reason'] ?? null;

        return is_string($reason) ? $reason : null;
    }

    /** Quote plus signed variation orders. */
    public function totalCents(): int
    {
        return $this->quote_cents + (int) $this->variationOrders()->where('status', 'signed')->sum('amount_cents');
    }

    /** @return array{quote_cents: int, total_cents: int, recorded_cents: int, confirmed_cents: int, balance_cents: int} */
    public function summary(): array
    {
        $total = $this->totalCents();
        $recorded = (int) $this->payments()->sum('amount_cents');
        $confirmed = (int) $this->payments()->where('client_status', 'confirmed')->sum('amount_cents');

        return [
            'quote_cents' => $this->quote_cents,
            'total_cents' => $total,
            'recorded_cents' => $recorded,
            'confirmed_cents' => $confirmed,
            'balance_cents' => $total - $confirmed,
        ];
    }

    public function shareUrl(): string
    {
        return url('/c/'.$this->share_token);
    }
}
