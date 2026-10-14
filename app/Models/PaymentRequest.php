<?php

namespace App\Models;

use App\Models\Concerns\ScopedThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $milestone_id
 * @property int $amount_cents
 * @property string|null $note
 * @property string $status
 * @property Carbon|null $decided_at
 * @property int|null $decided_by_client_id
 * @property string|null $reject_reason
 * @property string|null $verbal_approval_note
 * @property-read Milestone $milestone
 */
class PaymentRequest extends Model
{
    use ScopedThrough;

    public static function tenantParent(): string
    {
        return 'milestone';
    }

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Milestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
