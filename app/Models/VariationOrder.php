<?php

namespace App\Models;

use App\Models\Concerns\ScopedThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_id
 * @property string $number
 * @property string $description
 * @property string|null $reason
 * @property int $amount_cents
 * @property int|null $milestone_id
 * @property string $status
 * @property Carbon|null $signed_at
 * @property string|null $signature_path
 * @property string|null $signer_name
 * @property string|null $reject_reason
 * @property-read Job $job
 */
class VariationOrder extends Model
{
    use ScopedThrough;

    public static function tenantParent(): string
    {
        return 'job';
    }

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return HasMany<VoAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(VoAttachment::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
