<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/** Append-only audit trail.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $job_id
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string $action
 * @property Carbon|null $created_at
 * @property array<string, mixed>|null $meta
 */
class ActivityLog extends Model
{
    use BelongsToCompany;

    protected $table = 'activity_log';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** English label per action (also the translation key) for the activity feeds and the PDF record. */
    public const LABELS = [
        'job.created' => 'Job created',
        'job.updated' => 'Draft edited',
        'job.sent' => 'Job sent to client',
        'job.accepted' => 'Client accepted the job',
        'job.completed' => 'Job completed',
        'job.cancelled' => 'Job cancelled',
        'job.cancelled_draft' => 'Draft cancelled',
        'milestone.added' => 'Milestone added',
        'milestone.updated' => 'Milestone edited',
        'milestone.started' => 'Milestone started',
        'milestone.paid' => 'Milestone fully paid',
        'proof.added' => 'Proof added',
        'proof.deleted' => 'Proof removed',
        'payment_request.sent' => 'Payment requested',
        'payment_request.approved' => 'Client approved payment request',
        'payment_request.rejected' => 'Client rejected payment request',
        'payment_request.withdrawn' => 'Payment request withdrawn',
        'payment_request.approved_verbally' => 'Marked approved verbally',
        'client.reminded' => 'Reminder sent to client',
        'client.verified' => 'Client opened the link',
        'variation_order.created' => 'Variation order drafted',
        'variation_order.sent' => 'Variation order sent',
        'variation_order.signed' => 'Client signed variation order',
        'variation_order.rejected' => 'Client rejected variation order',
        'payment.recorded' => 'Payment recorded',
        'payment.confirmed' => 'Client confirmed payment',
        'payment.disputed' => 'Client disputed payment',
        'comment.added' => 'Comment added',
    ];

    /** The action in the current language. */
    public function label(): string
    {
        return __(self::LABELS[$this->action] ?? str_replace(['.', '_'], ' ', $this->action));
    }

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /** @return BelongsTo<Job, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity log is append-only.'));
        static::deleting(fn () => throw new LogicException('Activity log is append-only.'));
    }
}
