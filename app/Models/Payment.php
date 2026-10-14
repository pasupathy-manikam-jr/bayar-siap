<?php

namespace App\Models;

use App\Models\Concerns\ScopedThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_id
 * @property int|null $milestone_id
 * @property int $amount_cents
 * @property Carbon $paid_on
 * @property string $method
 * @property string|null $reference
 * @property string|null $receipt_path
 * @property string $client_status
 * @property string|null $dispute_note
 * @property Carbon|null $confirmed_at
 * @property-read Job $job
 * @property-read Milestone|null $milestone
 */
class Payment extends Model
{
    use ScopedThrough;

    public static function tenantParent(): string
    {
        return 'job';
    }

    protected $guarded = ['id'];

    protected $attributes = ['client_status' => 'pending'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'confirmed_at' => 'datetime'];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /** @return BelongsTo<Milestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }
}
