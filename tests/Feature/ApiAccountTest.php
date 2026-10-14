<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ContractorAlert;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_a_link_without_revealing_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()
            ->assertJsonPath('message', 'If that email has an account, a reset link is on its way.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_notifications_list_and_unread_count_for_the_app(): void
    {
        $user = User::factory()->create();
        $user->notify(new ContractorAlert('Payment confirmed', 'Puan Siti confirmed RM 100.00.', [], 'https://example.com/jobs/1', 1));
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')->assertJsonPath('unread_notifications', 1);
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.title', 'Payment confirmed')
            ->assertJsonPath('data.0.unread', true)
            ->assertJsonPath('next_page', null);
        $this->getJson('/api/v1/me')->assertJsonPath('unread_notifications', 0);
    }

    public function test_billing_url_reports_when_stripe_is_not_set_up(): void
    {
        config(['cashier.secret' => null]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/billing/checkout')->assertStatus(503);
        $this->postJson('/api/v1/billing/other')->assertNotFound();
    }
}
