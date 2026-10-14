<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->owner->company->forceFill(['stripe_id' => 'cus_123'])->save();
    }

    private function subscribe(string $status = 'active'): void
    {
        $this->owner->company->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_123', 'stripe_status' => $status, 'stripe_price' => 'price_solo', 'quantity' => 1,
        ]);
    }

    public function test_plan_follows_the_stripe_subscription(): void
    {
        $company = $this->owner->company;
        $this->subscribe();
        $company->syncPlanFromSubscription();
        $this->assertSame('solo', $company->plan);
        $this->assertSame(10, $company->activeJobLimit());

        $company->subscription()?->forceFill(['stripe_status' => 'canceled', 'ends_at' => now()->subMinute()])->save();
        $company->refresh()->syncPlanFromSubscription();
        $this->assertSame('free', $company->plan);
    }

    public function test_cancellation_webhook_downgrades_the_company(): void
    {
        $this->subscribe();
        $this->owner->company->syncPlanFromSubscription();

        $this->postJson('/stripe/webhook', [
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_123', 'customer' => 'cus_123']],
        ])->assertOk();

        $this->assertSame('free', $this->owner->company->refresh()->plan);
    }

    public function test_team_plan_set_by_hand_is_left_alone(): void
    {
        $company = $this->owner->company;
        $company->forceFill(['plan' => 'team'])->save();
        $company->syncPlanFromSubscription();

        $this->assertSame('team', $company->refresh()->plan);
    }

    public function test_upgrade_explains_when_stripe_is_not_configured(): void
    {
        config(['cashier.secret' => null]);

        $this->actingAs($this->owner)->post(route('billing.checkout'))->assertRedirect(route('company.edit'));
        $this->post(route('billing.portal'))->assertRedirect(route('company.edit'));
        $this->assertSame('free', $this->owner->company->refresh()->plan);
    }

    public function test_stripe_customer_is_the_company_with_the_owner_email(): void
    {
        $company = $this->owner->company;

        $this->assertInstanceOf(Company::class, Cashier::findBillable('cus_123'));
        $this->assertSame($company->name, $company->stripeName());
        $this->assertSame($this->owner->email, $company->stripeEmail());
    }
}
