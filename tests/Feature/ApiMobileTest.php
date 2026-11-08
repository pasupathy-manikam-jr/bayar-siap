<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Milestone;
use App\Models\User;
use App\Notifications\ContractorAlert;
use App\Services\JobFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** What the Android/iOS apps rely on beyond the basic job endpoints. */
class ApiMobileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private JobFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        // Real served local disk so the signed PUT upload route runs end to end.
        config(['filesystems.disks.local.root' => $root = storage_path('framework/testing/disks/api-mobile')]);
        Storage::forgetDisk('local');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

        $this->owner = User::factory()->create();
        $this->flow = app(JobFlow::class);
        Sanctum::actingAs($this->owner);
    }

    private function inProgressMilestone(): Milestone
    {
        $client = Client::create(['company_id' => $this->owner->company_id, 'name' => 'Puan Siti', 'phone' => '0123456789']);
        $job = $this->flow->createJob($this->owner, [
            'company_id' => $this->owner->company_id, 'client_id' => $client->id, 'title' => 'Kitchen', 'site_address' => 'Shah Alam', 'quote_cents' => 100_000,
        ], [['name' => 'Deposit', 'amount_cents' => 100_000]]);
        $this->flow->sendJob($job, $this->owner);
        $this->flow->acceptJob($job->refresh(), $client);
        $milestone = $job->milestones()->firstOrFail();
        $this->flow->startMilestone($milestone, $this->owner);

        return $milestone->refresh();
    }

    /** Upload bytes through the pre-signed URL; returns the storage path. */
    private function upload(Milestone $milestone, string $bytes): string
    {
        $upload = $this->postJson("/api/v1/milestones/{$milestone->id}/proofs/upload-url", ['extension' => 'jpg'])->assertOk()->json();
        $this->call('PUT', $upload['url'], [], [], [], [], $bytes)->assertNoContent();

        return $upload['path'];
    }

    public function test_api_answers_in_the_users_language_or_the_phones(): void
    {
        $this->putJson('/api/v1/me/locale', ['locale' => 'xx'])->assertUnprocessable();
        $this->putJson('/api/v1/me/locale', ['locale' => 'ms'])->assertOk()->assertJsonPath('locale', 'ms');

        $this->postJson('/api/v1/jobs', [])->assertUnprocessable()
            ->assertJsonPath('errors.title.0', __('validation.required', ['attribute' => 'title'], 'ms'));

        $this->owner->forceFill(['locale' => null])->save();
        $this->postJson('/api/v1/jobs', [], ['Accept-Language' => 'zh-CN'])->assertUnprocessable()
            ->assertJsonPath('errors.title.0', __('validation.required', ['attribute' => 'title'], 'zh'));
    }

    public function test_offline_retries_of_a_proof_never_create_a_duplicate(): void
    {
        $milestone = $this->inProgressMilestone();
        $uuid = (string) Str::uuid();
        $path = $this->upload($milestone, 'photo-bytes');
        $payload = ['type' => 'photo', 'path' => $path, 'sha256' => hash('sha256', 'photo-bytes'), 'captured_at' => now()->toIso8601String(), 'client_uuid' => $uuid];

        $first = $this->postJson("/api/v1/milestones/{$milestone->id}/proofs", $payload)->assertCreated()->json('id');
        $this->postJson("/api/v1/milestones/{$milestone->id}/proofs", $payload)->assertOk()->assertJsonPath('id', $first);

        // The phone lost the reply and re-uploaded the file under a new path: same proof back, the copy is deleted.
        $copy = $this->upload($milestone, 'photo-bytes');
        $this->postJson("/api/v1/milestones/{$milestone->id}/proofs", ['path' => $copy] + $payload)->assertOk()->assertJsonPath('id', $first);

        $this->assertSame(1, $milestone->proofs()->count());
        $this->assertTrue(Storage::exists($path));
        $this->assertFalse(Storage::exists($copy));
    }

    public function test_notifications_say_which_screen_to_open(): void
    {
        $milestone = $this->inProgressMilestone();
        $this->flow->addProof($milestone, $this->owner, ['type' => 'note', 'caption' => 'Done']);
        $request = $this->flow->requestPayment($milestone->refresh(), $this->owner, null);
        Notification::fake();

        $this->flow->approveRequest($request, $milestone->job->client);

        Notification::assertSentTo($this->owner, ContractorAlert::class, fn (ContractorAlert $alert) => $alert->target === ['type' => 'milestone', 'id' => $milestone->id]
            && $alert->toArray($this->owner)['target'] === ['type' => 'milestone', 'id' => $milestone->id]);
    }

    public function test_verbal_approval_and_comment_threads_over_the_api(): void
    {
        $milestone = $this->inProgressMilestone();
        $job = $milestone->job;
        $this->flow->addProof($milestone, $this->owner, ['type' => 'note', 'caption' => 'Done']);
        $request = $this->flow->requestPayment($milestone->refresh(), $this->owner, null);

        $this->postJson("/api/v1/payment-requests/{$request->id}/approve-verbally", ['note' => ''])->assertJsonValidationErrors('note');
        $this->postJson("/api/v1/payment-requests/{$request->id}/approve-verbally", ['note' => 'Phone call, 3pm'])->assertOk()
            ->assertJsonPath('verbal_approval_note', 'Phone call, 3pm');

        $this->flow->comment($milestone, $job->client, 'Looks good');
        $this->getJson("/api/v1/milestones/{$milestone->id}")->assertOk()
            ->assertJsonPath('comments.0.from', 'client')
            ->assertJsonPath('comments.0.author', 'Puan Siti')
            ->assertJsonMissingPath('comments.0.author.phone');

        $vo = $this->flow->createVariationOrder($job->refresh(), $this->owner, ['description' => 'Extra', 'amount_cents' => 1000]);
        $this->flow->comment($vo, $this->owner, 'Drawing attached');
        $this->getJson("/api/v1/jobs/{$job->id}")->assertOk()
            ->assertJsonPath('variation_orders.0.comments.0.body', 'Drawing attached')
            ->assertJsonPath('variation_orders.0.comments.0.from', 'contractor');
    }

    public function test_company_logo_uploads_as_multipart_post(): void
    {
        $this->post('/api/v1/company', ['name' => 'Ali Renovation', 'logo' => UploadedFile::fake()->image('logo.png')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('name', 'Ali Renovation');

        $this->assertTrue(Storage::exists((string) $this->owner->company->refresh()->logo_path));
    }
}
