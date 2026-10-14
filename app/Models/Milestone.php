<?php

namespace App\Models;

use App\Models\Concerns\ScopedThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_id
 * @property int|null $variation_order_id
 * @property int $sequence
 * @property string $name
 * @property string|null $description
 * @property int $amount_cents
 * @property Carbon|null $due_date
 * @property string $status
 * @property-read Job $job
 */
class Milestone extends Model
{
    use ScopedThrough;

    public static function tenantParent(): string
    {
        return 'job';
    }

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return HasMany<Proof, $this> */
    public function proofs(): HasMany
    {
        return $this->hasMany(Proof::class);
    }

    /** @return HasMany<PaymentRequest, $this> */
    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class)->latest('id');
    }

    /** @return HasOne<PaymentRequest, $this> */
    public function openRequest(): HasOne
    {
        return $this->hasOne(PaymentRequest::class)->where('status', 'open');
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** Proofs are editable only before a request is open and before approval. */
    public function proofsLocked(): bool
    {
        return ! in_array($this->status, ['planned', 'in_progress'], true);
    }
}
