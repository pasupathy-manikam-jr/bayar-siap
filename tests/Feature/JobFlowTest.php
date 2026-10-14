<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\Proof;
use App\Models\User;
use App\Models\VariationOrder;
use App\Services\JobFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class JobFlowTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $email = 'ali@example.com', string $plan = 'free'): User
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'Ali Renovation', 'name' => 'Ali', 'email' => $email, 'password' => 'secret123',
        ])->assertCreated();
        $user = User::where('email', $email)->first();
        $user->company->forceFill(['plan' => $plan])->save();
        $this->assertNotEmpty($res->json('token'));
        Sanctum::actingAs($user);

        return $user;
    }

    private function newJob(array $overrides = []): array
    {
        return $this->postJson('/api/v1/jobs', $overrides + [
            'client' => ['name' => 'Puan Siti', 'phone' => '60123456789'],
            'title' => 'Kitchen renovation',
            'site_address' => '12 Jalan Mawar, Shah Alam',
            'quote_cents' => 1_000_001,
            'template' => 'renovation',
        ])->assertCreated()->json();
    }

    /** Draft → sent → accepted by the client; returns the active job. */
    private function activeJob(): Job
    {
        $data = $this->newJob();
        $this->postJson("/api/v1/jobs/{$data['id']}/send")->assertOk()->assertJsonPath('status', 'sent');
        $job = Job::find($data['id']);
        app(JobFlow::class)->acceptJob($job, $job->client);

        return $job->refresh();
    }

    private function addPhotoProof(Milestone $milestone): void
    {
        $upload = $this->postJson("/api/v1/milestones/{$milestone->id}/proofs/upload-url", ['extension' => 'jpg'])->assertOk();
        $bytes = 'fake-jpeg-bytes-'.$milestone->id;
        $this->call('PUT', $upload->json('url'), [], [], [], [], $bytes)->assertNoContent();

        $this->postJson("/api/v1/milestones/{$milestone->id}/proofs", [
            'type' => 'photo', 'path' => $upload->json('path'), 'sha256' => hash('sha256', $bytes),
            'captured_at' => now()->subHour()->toIso8601String(), 'lat' => 3.07, 'lng' => 101.52,
        ])->assertCreated();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Real served local disk (not Storage::fake) so the signed PUT upload route is exercised end to end.
        config(['filesystems.disks.local.root' => $root = storage_path('framework/testing/disks/local')]);
        Storage::forgetDisk('local');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));
    }

    public function test_template_milestones_sum_exactly_to_quote(): void
    {
        $this->contractor();
        $job = $this->newJob();

        $this->assertCount(4, $job['milestones']);
        $this->assertSame(1_000_001, array_sum(array_column($job['milestones'], 'amount_cents')));
        $this->assertSame('draft', $job['status']);
    }

    public function test_cannot_send_when_milestones_do_not_sum_to_quote(): void
    {
        $this->contractor();
        $job = $this->newJob(['template' => null, 'milestones' => [['name' => 'Deposit', 'amount_cents' => 500]]]);

        $this->postJson("/api/v1/jobs/{$job['id']}/send")->assertUnprocessable()->assertJsonValidationErrors('rule');
    }

    public function test_free_plan_allows_one_active_job(): void
    {
        $this->contractor();
        $this->postJson("/api/v1/jobs/{$this->newJob()['id']}/send")->assertOk();
        $this->postJson("/api/v1/jobs/{$this->newJob()['id']}/send")->assertUnprocessable();
    }

    public function test_tenants_cannot_see_each_others_jobs(): void
    {
        $this->contractor('a@example.com');
        $jobId = $this->newJob()['id'];

        $this->contractor('b@example.com');
        $this->getJson("/api/v1/jobs/{$jobId}")->assertNotFound();
        $this->getJson('/api/v1/jobs')->assertOk()->assertJsonCount(0);
        $milestoneId = Milestone::withoutGlobalScopes()->where('job_id', $jobId)->value('id');
        $this->getJson("/api/v1/milestones/{$milestoneId}")->assertNotFound();
    }

    public function test_payment_request_requires_proof_and_locks_it(): void
    {
        $this->contractor();
        $milestone = $this->activeJob()->milestones->first();

        $this->postJson("/api/v1/milestones/{$milestone->id}/start")->assertOk();
        $this->postJson("/api/v1/milestones/{$milestone->id}/payment-requests")->assertUnprocessable();

        $this->addPhotoProof($milestone);
        $this->postJson("/api/v1/milestones/{$milestone->id}/payment-requests")->assertCreated();
        $this->postJson("/api/v1/milestones/{$milestone->id}/payment-requests")->assertUnprocessable(); // one open at a time

        // Proofs are read-only while the request is open.
        $proof = $milestone->proofs()->first();
        $this->deleteJson("/api/v1/proofs/{$proof->id}")->assertUnprocessable();
        $this->postJson("/api/v1/milestones/{$milestone->id}/proofs/upload-url", ['extension' => 'jpg'])->assertUnprocessable();
    }

    public function test_proof_hash_must_match_uploaded_bytes(): void
    {
        $this->contractor();
        $milestone = $this->activeJob()->milestones->first();
        $upload = $this->postJson("/api/v1/milestones/{$milestone->id}/proofs/upload-url", ['extension' => 'jpg'])->json();
        $this->call('PUT', $upload['url'], [], [], [], [], 'real-bytes')->assertNoContent();

        $this->postJson("/api/v1/milestones/{$milestone->id}/proofs", [
            'type' => 'photo', 'path' => $upload['path'], 'sha256' => hash('sha256', 'other-bytes'), 'captured_at' => now()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('sha256');

        $this->postJson("/api/v1/milestones/{$milestone->id}/proofs", [
            'type' => 'photo', 'path' => 'proofs/999/1/x.jpg', 'sha256' => hash('sha256', 'real-bytes'), 'captured_at' => now()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('path');
    }

    public function test_rejection_needs_reason_and_returns_milestone_to_in_progress(): void
    {
        $this->contractor();
        $job = $this->activeJob();
        $milestone = $job->milestones->first();
        $this->postJson("/api/v1/milestones/{$milestone->id}/start");
        $this->addPhotoProof($milestone);
        $requestId = $this->postJson("/api/v1/milestones/{$milestone->id}/payment-requests")->json('id');

        $flow = app(JobFlow::class);
        $request = PaymentRequest::find($requestId);
        try {
            $flow->rejectRequest($request, $job->client, '  ');
            $this->fail('Rejection without reason was accepted.');
        } catch (ValidationException) {
        }
        $flow->rejectRequest($request, $job->client, 'Tiles are cracked');

        $this->assertSame('in_progress', $milestone->refresh()->status);
        $this->assertSame('Tiles are cracked', $request->refresh()->reject_reason);
        // Proof editable again after rejection.
        $this->addPhotoProof($milestone);
        $this->assertTrue(ActivityLog::where('action', 'payment_request.rejected')->exists());
    }

    public function test_signed_variation_orders_keep_milestones_equal_to_total(): void
    {
        $this->contractor();
        $job = $this->activeJob();
        $flow = app(JobFlow::class);

        $addId = $this->postJson("/api/v1/jobs/{$job->id}/variation-orders", ['description' => 'Extra cabinet', 'amount_cents' => 250_000])
            ->assertCreated()->assertJsonPath('number', 'VO-001')->json('id');
        $last = $job->milestones->last();
        $deductId = $this->postJson("/api/v1/jobs/{$job->id}/variation-orders", ['description' => 'Skip backsplash', 'amount_cents' => -50_000, 'milestone_id' => $last->id])
            ->assertCreated()->json('id');
        $this->postJson("/api/v1/jobs/{$job->id}/variation-orders", ['description' => 'Bad', 'amount_cents' => -50_000])->assertUnprocessable();

        foreach ([$addId, $deductId] as $id) {
            $this->postJson("/api/v1/variation-orders/{$id}/send")->assertOk();
            $flow->signVariationOrder(VariationOrder::find($id), $job->client, 'Siti binti Ahmad', null);
        }

        $this->assertSame(1_000_001 + 200_000, $job->totalCents());
        $this->assertSame($job->totalCents(), (int) $job->milestones()->sum('amount_cents'));
        $this->assertCount(5, $job->milestones()->get());
        $this->assertSame($last->amount_cents - 50_000, $last->refresh()->amount_cents);
    }

    public function test_amounts_locked_after_send(): void
    {
        $this->contractor();
        $milestone = $this->activeJob()->milestones->first();

        $this->putJson("/api/v1/milestones/{$milestone->id}", ['amount_cents' => 1])->assertUnprocessable();
        $this->putJson("/api/v1/milestones/{$milestone->id}", ['name' => 'Deposit (30%)'])->assertOk();
        $this->postJson("/api/v1/jobs/{$milestone->job_id}/milestones", ['name' => 'Sneaky', 'amount_cents' => 100])->assertUnprocessable();
    }

    public function test_job_completes_when_all_milestones_paid_and_payments_confirmed(): void
    {
        $this->contractor();
        $job = $this->activeJob();
        $flow = app(JobFlow::class);

        foreach ($job->milestones as $milestone) {
            $this->postJson("/api/v1/milestones/{$milestone->id}/start")->assertOk();
            $this->addPhotoProof($milestone);
            $requestId = $this->postJson("/api/v1/milestones/{$milestone->id}/payment-requests")->json('id');
            $flow->approveRequest(PaymentRequest::find($requestId), $job->client);

            // Two partial payments per milestone.
            $half = intdiv($milestone->amount_cents, 2);
            foreach ([$half, $milestone->amount_cents - $half] as $amount) {
                $paymentId = $this->postJson("/api/v1/jobs/{$job->id}/payments", [
                    'milestone_id' => $milestone->id, 'amount_cents' => $amount, 'paid_on' => today()->toDateString(), 'method' => 'transfer',
                ])->assertCreated()->json('id');
                $this->assertSame('approved', $milestone->refresh()->status);
                $flow->confirmPayment(Payment::find($paymentId), $job->client);
            }
            $this->assertSame('paid', $milestone->refresh()->status);
        }

        $this->assertSame('completed', $job->refresh()->status);
        $this->assertSame(0, $job->summary()['balance_cents']);
    }

    public function test_activity_log_is_append_only(): void
    {
        $this->contractor();
        $this->newJob();
        $log = ActivityLog::first();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_client_records_are_scoped_to_company(): void
    {
        $this->contractor('a@example.com');
        $this->newJob();
        $this->contractor('b@example.com');

        $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(0);
        $this->assertSame(1, Client::withoutGlobalScopes()->count());
    }

    public function test_api_upload_over_storage_limit_is_refused_and_the_file_deleted(): void
    {
        $user = $this->contractor();
        $milestone = $this->activeJob()->milestones->first();
        $this->postJson("/api/v1/milestones/{$milestone->id}/start");
        Proof::create(['milestone_id' => $milestone->id, 'uploaded_by' => $user->id, 'type' => 'file', 'path' => 'old.pdf', 'bytes' => 5 * 1024 ** 3]);

        $upload = $this->postJson("/api/v1/milestones/{$milestone->id}/proofs/upload-url", ['extension' => 'jpg'])->json();
        $this->call('PUT', $upload['url'], [], [], [], [], 'photo-bytes')->assertNoContent();
        $this->postJson("/api/v1/milestones/{$milestone->id}/proofs", [
            'type' => 'photo', 'path' => $upload['path'], 'sha256' => hash('sha256', 'photo-bytes'), 'captured_at' => now()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('rule');

        $this->assertFalse(Storage::exists($upload['path']));
    }

    public function test_api_can_cancel_a_job(): void
    {
        $this->contractor();
        $job = $this->activeJob();

        $this->postJson("/api/v1/jobs/{$job->id}/cancel")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/jobs/{$job->id}/cancel", ['reason' => 'Client withdrew'])->assertOk()->assertJsonPath('status', 'cancelled');
    }
}
