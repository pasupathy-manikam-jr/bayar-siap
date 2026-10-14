<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Job;
use App\Models\User;
use App\Notifications\ClientAlert;
use App\Notifications\ContractorAlert;
use App\Services\JobFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private JobFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->flow = app(JobFlow::class);
    }

    private function job(?string $clientEmail = null): Job
    {
        $client = Client::create(['company_id' => $this->owner->company_id, 'name' => 'Puan Siti', 'phone' => '0123456789', 'email' => $clientEmail]);

        return $this->flow->createJob($this->owner, [
            'company_id' => $this->owner->company_id, 'client_id' => $client->id,
            'title' => 'Kitchen', 'site_address' => 'Shah Alam', 'quote_cents' => 100_000,
        ], [['name' => 'Deposit', 'amount_cents' => 100_000, 'due_date' => today()->addDays(3)->toDateString()]]);
    }

    private function activeJob(): Job
    {
        $job = $this->job();
        $this->flow->sendJob($job, $this->owner);
        $this->flow->acceptJob($job->refresh(), $job->client);

        return $job->refresh();
    }

    public function test_client_is_texted_with_a_deep_link_and_emailed_when_on_file(): void
    {
        Notification::fake();
        $job = $this->job('siti@example.com');
        $this->flow->sendJob($job, $this->owner);

        Notification::assertSentTo($job->client, ClientAlert::class, fn (ClientAlert $alert, array $channels) => str_contains($alert->toSms(), $job->shareUrl())
            && in_array('mail', $channels, true));

        $this->flow->acceptJob($job->refresh(), $job->client);
        $milestone = $job->milestones()->firstOrFail();
        $this->flow->startMilestone($milestone, $this->owner);
        $this->flow->addProof($milestone->refresh(), $this->owner, ['type' => 'note', 'caption' => 'Done']);
        $request = $this->flow->requestPayment($milestone->refresh(), $this->owner, null);

        Notification::assertSentTo($job->client, ClientAlert::class, fn (ClientAlert $alert) => $alert->url === route('client.payment-requests.show', [$job->share_token, $request->id])
            && str_contains($alert->text(), 'RM 1,000.00'));
        Notification::assertSentTo($this->owner, ContractorAlert::class, fn (ContractorAlert $alert) => $alert->translatedTitle() === 'Puan Siti accepted the job');
    }

    public function test_contractor_sees_client_decisions_in_notifications_page(): void
    {
        $job = $this->activeJob();
        $milestone = $job->milestones()->firstOrFail();
        $this->flow->startMilestone($milestone, $this->owner);
        $this->flow->addProof($milestone->refresh(), $this->owner, ['type' => 'note', 'caption' => 'Done']);
        $this->travel(1)->minutes();
        $this->flow->rejectRequest($this->flow->requestPayment($milestone->refresh(), $this->owner, null), $job->client, 'Tiles cracked');

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 2));

        $this->get(route('notifications'))->assertInertia(fn (Assert $page) => $page
            ->component('notifications')
            ->where('notifications.0.title', 'Payment request rejected')
            ->where('notifications.0.unread', true)
            ->where('notifications.0.url', route('milestones.show', $milestone))
            ->where('notifications.0.body', fn (string $body) => str_contains($body, '“Tiles cracked”')));

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 0));
    }

    public function test_reminders_nudge_waiting_clients_once_per_48_hours_and_warn_due_milestones(): void
    {
        $job = $this->activeJob();
        $this->flow->recordPayment($job, $this->owner, ['amount_cents' => 5_000, 'paid_on' => today(), 'method' => 'cash']);
        Notification::fake();

        $this->artisan('jobproof:reminders')->assertSuccessful();
        Notification::assertNotSentTo($job->client, ClientAlert::class);
        Notification::assertSentTo($this->owner, ContractorAlert::class, fn (ContractorAlert $alert) => $alert->translatedTitle() === 'Milestone due in 3 days');

        $this->travel(49)->hours();
        $this->artisan('jobproof:reminders');
        $this->artisan('jobproof:reminders');
        Notification::assertSentToTimes($job->client, ClientAlert::class, 1);
        Notification::assertSentTo($job->client, ClientAlert::class, fn (ClientAlert $alert) => str_contains($alert->text(), '1 item is waiting'));
    }

    public function test_verbal_approval_needs_a_note_is_flagged_and_tells_the_client(): void
    {
        $job = $this->activeJob();
        $milestone = $job->milestones()->firstOrFail();
        $this->flow->startMilestone($milestone, $this->owner);
        $this->flow->addProof($milestone->refresh(), $this->owner, ['type' => 'note', 'caption' => 'Done']);
        $request = $this->flow->requestPayment($milestone->refresh(), $this->owner, null);
        Notification::fake();

        $this->actingAs($this->owner)
            ->post(route('payment-requests.approve-verbally', $request), ['note' => ''])
            ->assertSessionHasErrors(['note' => 'Note how and when the client approved.']);
        $this->post(route('payment-requests.approve-verbally', $request), ['note' => 'Phone call, 13 Oct 3pm'])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame('Phone call, 13 Oct 3pm', $request->verbal_approval_note);
        $this->assertNull($request->decided_by_client_id);
        $this->assertSame('approved', $milestone->refresh()->status);
        Notification::assertSentTo($job->client, ClientAlert::class, fn (ClientAlert $alert) => str_contains($alert->text(), 'approved by you verbally'));
        Notification::assertNotSentTo($this->owner, ContractorAlert::class);
    }
}
