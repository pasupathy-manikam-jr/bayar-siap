<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $logo_path
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_no
 * @property string $plan
 * @property Carbon|null $trial_ends_at
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Billable, HasFactory;

    /** Paid plan sold through Stripe; Team is phase 2 and set by hand. */
    public const PAID_PLAN = 'solo';

    public const PLAN_ACTIVE_JOB_LIMITS = ['free' => 1, 'solo' => 10, 'team' => null];

    /** Photo/file storage per plan in GB (spec pricing table); null = unlimited. */
    public const PLAN_STORAGE_GB = ['free' => 5, 'solo' => 50, 'team' => null];

    protected $guarded = ['id'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime'];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** The Stripe subscription decides Free vs Solo; a hand-set Team plan is left alone. */
    public function syncPlanFromSubscription(): void
    {
        if ($this->plan === 'team') {
            return;
        }
        $this->forceFill(['plan' => $this->subscribed() ? self::PAID_PLAN : 'free'])->save();
    }

    public static function billingConfigured(): bool
    {
        return filled(config('cashier.secret')) && filled(config('services.stripe.solo_price'));
    }

    /** Stripe Checkout page for upgrading to Solo. */
    public function soloCheckoutUrl(string $successUrl, string $cancelUrl): string
    {
        return (string) $this->newSubscription('default', (string) config('services.stripe.solo_price'))
            ->checkout(['success_url' => $successUrl, 'cancel_url' => $cancelUrl])
            ->asStripeCheckoutSession()->url;
    }

    public function stripeName(): string
    {
        return $this->name;
    }

    public function stripeEmail(): ?string
    {
        return $this->users()->where('role', 'owner')->oldest('id')->value('email');
    }

    public function storageLimitBytes(): ?int
    {
        $gb = array_key_exists($this->plan, self::PLAN_STORAGE_GB) ? self::PLAN_STORAGE_GB[$this->plan] : 5;

        return $gb === null ? null : $gb * 1024 ** 3;
    }

    /** Originals of proofs and variation-order attachments (thumbnails are small and not counted). */
    public function storageUsedBytes(): int
    {
        $proofs = DB::table('proofs')->join('milestones', 'milestones.id', '=', 'proofs.milestone_id')
            ->join('jobs', 'jobs.id', '=', 'milestones.job_id')->where('jobs.company_id', $this->id)->sum('proofs.bytes');
        $attachments = DB::table('vo_attachments')->join('variation_orders', 'variation_orders.id', '=', 'vo_attachments.variation_order_id')
            ->join('jobs', 'jobs.id', '=', 'variation_orders.job_id')->where('jobs.company_id', $this->id)->sum('vo_attachments.bytes');

        return (int) $proofs + (int) $attachments;
    }

    public function activeJobLimit(): ?int
    {
        return self::PLAN_ACTIVE_JOB_LIMITS[$this->plan] ?? 1;
    }
}
