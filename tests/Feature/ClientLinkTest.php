<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Http\Middleware\ResolveClientLink;
use App\Models\Client;
use App\Models\Company;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Models\VariationOrder;
use App\Services\JobFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{phone: string, message: string}> */
    private array $sms = [];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.local.root' => $root = storage_path('framework/testing/disks/client-link')]);
        Storage::forgetDisk('local');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

        $this->app->instance(SmsSender::class, new class($this->sms) implements SmsSender
        {
            /** @param list<array{phone: string, message: string}> $sent */
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = ['phone' => $phone, 'message' => $message];
            }
        });

        // Solo plan: these tests open several jobs at once.
        $this->owner = User::factory()->for(Company::factory()->state(['plan' => 'solo']))->create();
    }

    /** A job sent to its client, with two milestones adding up to the quote. */
    private function sentJob(): Job
    {
        $client = Client::firstOrCreate(['company_id' => $this->owner->company_id, 'phone' => '+60 12-345 6789'], ['name' => 'Puan Siti']);
        $job = app(JobFlow::class)->createJob($this->owner, [
            'company_id' => $this->owner->company_id, 'client_id' => $client->id,
            'title' => 'Kitchen renovation', 'site_address' => 'Shah Alam', 'quote_cents' => 1_000_000,
        ], [['name' => 'Deposit', 'percent' => 30], ['name' => 'Handover', 'percent' => 70]]);
        app(JobFlow::class)->sendJob($job, $this->owner);

        return $job->refresh();
    }

    private function url(Job $job, string $path = ''): string
    {
        return '/c/'.$job->share_token.$path;
    }

    /** Runs the OTP flow and keeps the device cookie for later requests. */
    private function verify(Job $job): void
    {
        $this->post($this->url($job, '/otp/send'))->assertRedirect($this->url($job));
        preg_match('/\b(\d{6})\b/', end($this->sms)['message'], $m);

        $response = $this->post($this->url($job, '/otp/verify'), ['code' => $m[1]])->assertRedirect($this->url($job));
        $this->withCookie(ResolveClientLink::COOKIE, (string) $response->getCookie(ResolveClientLink::COOKIE)?->getValue());
    }

    /** Puts the first milestone into an open payment request (with one note proof). */
    private function openRequest(Job $job): PaymentRequest
    {
        $flow = app(JobFlow::class);
        $milestone = $job->milestones()->first();
        $flow->startMilestone($milestone, $this->owner);
        $flow->addProof($milestone->refresh(), $this->owner, ['type' => 'note', 'caption' => 'Cabinets installed']);

        return $flow->requestPayment($milestone->refresh(), $this->owner, 'Deposit due');
    }

    public function test_unverified_visitor_sees_otp_page_with_masked_phone(): void
    {
        $job = $this->sentJob();

        $this->get($this->url($job))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('client/verify')
            ->where('maskedPhone', '•••••••6789')
            ->where('codeSent', false)
            ->missing('milestones'));
    }

    public function test_draft_and_unknown_links_are_not_found(): void
    {
        $job = $this->sentJob();
        $job->forceFill(['status' => 'draft'])->save();

        $this->get($this->url($job))->assertNotFound();
        $this->get('/c/not-a-real-token')->assertNotFound();
    }

    public function test_otp_flow_sends_code_to_client_phone_and_opens_the_job(): void
    {
        $job = $this->sentJob();

        $this->post($this->url($job, '/otp/send'));
        $this->assertSame('+60 12-345 6789', end($this->sms)['phone']);
        $this->post($this->url($job, '/otp/verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post($this->url($job, '/otp/verify'), ['code' => 'abc'])->assertSessionHasErrors(['code' => 'The code field must be 6 digits.']);

        $this->verify($job);

        $this->get($this->url($job))->assertInertia(fn (Assert $page) => $page
            ->component('client/show')
            ->where('job.title', 'Kitchen renovation')
            ->where('summary.total_cents', 1_000_000)
            ->has('milestones', 2)
            ->where('auth.user', null));
    }

    public function test_code_locks_after_five_wrong_attempts(): void
    {
        $job = $this->sentJob();
        $this->post($this->url($job, '/otp/send'));
        preg_match('/\b(\d{6})\b/', end($this->sms)['message'], $m);

        foreach (range(1, 5) as $i) {
            $this->post($this->url($job, '/otp/verify'), ['code' => $m[1] === '111111' ? '222222' : '111111']);
        }

        $this->post($this->url($job, '/otp/verify'), ['code' => $m[1]])
            ->assertSessionHasErrors(['code' => 'Too many wrong attempts. Request a new code.']);
    }

    public function test_actions_require_a_verified_session(): void
    {
        $job = $this->sentJob();

        $this->post($this->url($job, '/accept'))->assertRedirect($this->url($job));
        $this->assertSame('sent', $job->refresh()->status);
    }

    public function test_client_accepts_then_approves_or_rejects_with_reason(): void
    {
        $job = $this->sentJob();
        $this->verify($job);

        $this->post($this->url($job, '/accept'))->assertRedirect($this->url($job));
        $this->assertSame('active', $job->refresh()->status);

        $request = $this->openRequest($job);
        $this->get($this->url($job, "/payment-requests/{$request->id}"))->assertInertia(fn (Assert $page) => $page
            ->component('client/payment-request')
            ->where('paymentRequest.amount_cents', 300_000)
            ->has('proofs', 1));

        $this->post($this->url($job, "/payment-requests/{$request->id}/reject"), ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'The reason field is required.']);
        $this->post($this->url($job, "/payment-requests/{$request->id}/reject"), ['reason' => 'Doors misaligned']);
        $this->assertSame('in_progress', $request->milestone->refresh()->status);

        $second = app(JobFlow::class)->requestPayment($request->milestone, $this->owner, null);
        $this->post($this->url($job, "/payment-requests/{$second->id}/approve"))->assertRedirect($this->url($job));
        $this->assertSame('approved', $second->milestone->refresh()->status);
        $this->assertSame($job->client_id, $second->refresh()->decided_by_client_id);
    }

    public function test_client_cannot_reach_another_jobs_items(): void
    {
        $job = $this->sentJob();
        $other = $this->sentJob();
        app(JobFlow::class)->acceptJob($other, $other->client);
        $otherRequest = $this->openRequest($other);

        $this->verify($job);

        $this->get($this->url($job, "/payment-requests/{$otherRequest->id}"))->assertNotFound();
        $this->post($this->url($job, "/payment-requests/{$otherRequest->id}/approve"))->assertNotFound();
        $this->assertSame('open', $otherRequest->refresh()->status);
    }

    public function test_device_cookie_for_one_job_does_not_open_another(): void
    {
        $job = $this->sentJob();
        $other = $this->sentJob();
        $this->verify($job);

        $this->get($this->url($other))->assertInertia(fn (Assert $page) => $page->component('client/verify'));
    }

    public function test_client_signs_variation_order_with_signature_and_total_updates(): void
    {
        $job = $this->sentJob();
        $this->verify($job);
        $this->post($this->url($job, '/accept'));
        $vo = app(JobFlow::class)->createVariationOrder($job, $this->owner, ['description' => 'Extra cabinet', 'amount_cents' => 50_000]);
        app(JobFlow::class)->sendVariationOrder($vo, $this->owner);

        $this->post($this->url($job, "/variation-orders/{$vo->id}/sign"), ['signer_name' => 'Siti'])
            ->assertSessionHasErrors(['agree' => 'Tick the box to confirm you agree to this variation.']);
        $this->post($this->url($job, "/variation-orders/{$vo->id}/sign"), ['signer_name' => 'Siti', 'agree' => true, 'signature' => 'data:image/png;base64,bm90IGEgcG5n'])
            ->assertSessionHasErrors('signature');

        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $this->post($this->url($job, "/variation-orders/{$vo->id}/sign"), ['signer_name' => 'Siti binti Ahmad', 'agree' => true, 'signature' => $png])
            ->assertRedirect($this->url($job));

        $vo->refresh();
        $this->assertSame('signed', $vo->status);
        $this->assertTrue(Storage::exists((string) $vo->signature_path));
        $this->assertSame(1_050_000, $job->totalCents());
        $this->assertSame(1_050_000, (int) Milestone::where('job_id', $job->id)->sum('amount_cents'));
    }

    public function test_draft_variation_orders_are_hidden_from_client(): void
    {
        $job = $this->sentJob();
        $this->verify($job);
        $this->post($this->url($job, '/accept'));
        $vo = app(JobFlow::class)->createVariationOrder($job, $this->owner, ['description' => 'Not yet sent', 'amount_cents' => 10_000]);

        $this->get($this->url($job, "/variation-orders/{$vo->id}"))->assertNotFound();
        $this->assertSame('draft', VariationOrder::find($vo->id)?->status);
    }

    public function test_client_confirms_and_disputes_payments(): void
    {
        $job = $this->sentJob();
        $this->verify($job);
        $this->post($this->url($job, '/accept'));
        $job->refresh();
        $flow = app(JobFlow::class);
        $first = $flow->recordPayment($job, $this->owner, ['amount_cents' => 100_000, 'paid_on' => today(), 'method' => 'transfer']);
        $second = $flow->recordPayment($job, $this->owner, ['amount_cents' => 5_000, 'paid_on' => today(), 'method' => 'cash']);

        $this->post($this->url($job, "/payments/{$first->id}/confirm"))->assertRedirect($this->url($job));
        $this->post($this->url($job, "/payments/{$second->id}/dispute"), ['note' => ''])->assertSessionHasErrors('note');
        $this->post($this->url($job, "/payments/{$second->id}/dispute"), ['note' => 'Never received']);

        $this->assertSame('confirmed', $first->refresh()->client_status);
        $this->assertSame('disputed', $second->refresh()->client_status);
        $this->assertSame(100_000, $job->summary()['confirmed_cents']);
    }

    public function test_contractor_signed_in_on_same_browser_does_not_hide_the_client_link(): void
    {
        $job = $this->sentJob();
        $this->actingAs(User::factory()->for(Company::factory())->create());

        $this->get($this->url($job))->assertOk()->assertInertia(fn (Assert $page) => $page->component('client/verify'));
    }

    public function test_job_record_pdf_downloads_for_client_and_contractor(): void
    {
        $job = $this->sentJob();
        $this->verify($job);
        $this->post($this->url($job, '/accept'));
        $this->openRequest($job);

        $this->get($this->url($job, '/record.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->owner, 'sanctum')
            ->get("/api/v1/jobs/{$job->id}/record.pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_client_and_contractor_share_a_comment_thread(): void
    {
        $job = $this->sentJob();
        $this->verify($job);
        $this->post($this->url($job, '/accept'));
        $request = $this->openRequest($job->refresh());
        $milestone = $request->milestone;

        $this->post($this->url($job, '/comments'), ['commentable_type' => 'milestone', 'commentable_id' => $milestone->id, 'body' => ''])
            ->assertSessionHasErrors(['body' => 'The comment field is required.']);
        $this->post($this->url($job, '/comments'), ['commentable_type' => 'milestone', 'commentable_id' => $milestone->id, 'body' => 'Is the sink included?'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->post(route('comments.store'), ['commentable_type' => 'milestone', 'commentable_id' => $milestone->id, 'body' => 'Yes, sink included.'])
            ->assertSessionHasNoErrors();
        $this->get(route('milestones.show', $milestone))->assertInertia(fn (Assert $page) => $page
            ->where('comments.0.from', 'client')
            ->where('comments.0.author', 'Puan Siti')
            ->where('comments.1.from', 'contractor'));

        $this->get($this->url($job, "/payment-requests/{$request->id}"))->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.1.body', 'Yes, sink included.'));
    }

    public function test_client_cannot_comment_on_another_jobs_milestone(): void
    {
        $job = $this->sentJob();
        $other = $this->sentJob();
        $this->verify($job);

        $this->post($this->url($job, '/comments'), [
            'commentable_type' => 'milestone',
            'commentable_id' => $other->milestones()->value('id'),
            'body' => 'Hello',
        ])->assertNotFound();
    }

    public function test_client_still_sees_a_cancelled_job_and_can_confirm_payments_but_not_sign(): void
    {
        $job = $this->sentJob();
        $this->verify($job);
        $this->post($this->url($job, '/accept'));
        $flow = app(JobFlow::class);
        $payment = $flow->recordPayment($job->refresh(), $this->owner, ['amount_cents' => 1_000, 'paid_on' => today(), 'method' => 'cash']);
        $vo = $flow->createVariationOrder($job, $this->owner, ['description' => 'Extra', 'amount_cents' => 1_000]);
        $flow->sendVariationOrder($vo, $this->owner);
        $flow->cancelJob($job->refresh(), $this->owner, 'Project stopped');

        $this->get($this->url($job))->assertInertia(fn (Assert $page) => $page
            ->component('client/show')
            ->where('job.status', 'cancelled')
            ->where('job.cancel_reason', 'Project stopped'));
        $this->post($this->url($job, "/variation-orders/{$vo->id}/sign"), ['signer_name' => 'Siti', 'agree' => true])->assertSessionHasErrors('rule');
        $this->post($this->url($job, "/variation-orders/{$vo->id}/reject"), ['reason' => 'No'])->assertSessionHasErrors('rule');
        $this->post($this->url($job, "/payments/{$payment->id}/confirm"))->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $payment->refresh()->client_status);
    }
}
