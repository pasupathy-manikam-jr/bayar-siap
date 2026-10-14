<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Proof;
use App\Models\User;
use App\Notifications\ClientAlert;
use App\Services\JobFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractorWebTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.disks.local.root' => $root = storage_path('framework/testing/disks/contractor-web')]);
        Storage::forgetDisk('local');
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));

        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);
    }

    /** @param array<string, mixed> $overrides */
    private function storeJob(array $overrides = []): Job
    {
        $this->post(route('jobs.store'), $overrides + [
            'client_name' => 'Puan Siti',
            'client_phone' => '012-345 6789',
            'title' => 'Kitchen renovation',
            'site_address' => 'Shah Alam',
            'quote' => '10000.50',
            'start_date' => '2026-10-01',
            'target_date' => '2026-12-01',
            'milestones' => [
                ['name' => 'Deposit', 'amount' => '3000.00', 'due_date' => '2026-10-05'],
                ['name' => 'Handover', 'amount' => '7000.50', 'due_date' => null],
            ],
        ])->assertSessionHasNoErrors();

        return Job::latest('id')->firstOrFail();
    }

    private function activeJob(): Job
    {
        $job = $this->storeJob();
        $this->post(route('jobs.send', $job))->assertSessionHasNoErrors();
        app(JobFlow::class)->acceptJob($job->refresh(), $job->client);

        return $job->refresh();
    }

    public function test_dashboard_and_jobs_list_show_only_own_company(): void
    {
        $job = $this->storeJob();
        $other = User::factory()->for(Company::factory())->create();

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('stats')
            ->has('clientActivity'));
        $this->get(route('jobs.index'))->assertInertia(fn (Assert $page) => $page
            ->component('jobs/index')
            ->has('jobs.data', 1)
            ->where('jobs.data.0.title', 'Kitchen renovation'));
        $this->get(route('guide'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('guide'));
        $this->get(route('jobs.index', ['status' => 'active']))->assertInertia(fn (Assert $page) => $page->has('jobs.data', 0));

        $this->actingAs($other)->get(route('jobs.show', $job))->assertNotFound();
        $this->get(route('milestones.show', $job->milestones()->withoutGlobalScopes()->first()))->assertNotFound();
    }

    public function test_job_form_stores_ringgit_as_sen_and_creates_the_client(): void
    {
        $job = $this->storeJob();

        $this->assertSame('draft', $job->status);
        $this->assertSame(1_000_050, $job->quote_cents);
        $this->assertSame([300_000, 700_050], $job->milestones()->pluck('amount_cents')->all());
        $this->assertSame('Puan Siti', $job->client->name);
        $this->assertSame('2026-10-05', $job->milestones()->first()?->due_date?->toDateString());
    }

    public function test_job_form_validation_uses_laravel_messages(): void
    {
        $this->post(route('jobs.store'), ['quote' => 'abc', 'milestones' => []])
            ->assertSessionHasErrors([
                'title' => 'The title field is required.',
                'client_name' => 'The client name field is required when client is not present.',
                'quote' => 'The quote field must have 0-2 decimal places.',
                'milestones' => 'Add at least one milestone.',
            ]);

        $this->post(route('jobs.store'), ['milestones' => [['name' => '', 'amount' => '']]])
            ->assertSessionHasErrors(['milestones.0.name' => 'The milestone name field is required.']);
    }

    public function test_draft_can_be_edited_and_sent_only_when_schedule_matches_quote(): void
    {
        $job = $this->storeJob(['milestones' => [['name' => 'Deposit', 'amount' => '100']]]);

        $this->post(route('jobs.send', $job))->assertSessionHasErrors('rule');

        $this->get(route('jobs.edit', $job))->assertInertia(fn (Assert $page) => $page
            ->component('jobs/form')
            ->where('job.milestones.0.amount', '100.00'));
        $this->put(route('jobs.update', $job), [
            'client_id' => $job->client_id,
            'title' => 'Kitchen renovation',
            'site_address' => 'Shah Alam',
            'quote' => '500',
            'milestones' => [['name' => 'Deposit', 'amount' => '200'], ['name' => 'Rest', 'amount' => '300']],
        ])->assertRedirect(route('jobs.show', $job));
        $this->assertCount(2, $job->milestones()->get());

        $this->post(route('jobs.send', $job))->assertSessionHasNoErrors();
        $this->assertSame('sent', $job->refresh()->status);
        $this->get(route('jobs.edit', $job))->assertRedirect(route('jobs.show', $job));
        $this->get(route('jobs.show', $job))->assertInertia(fn (Assert $page) => $page
            ->where('job.share_url', $job->shareUrl()));
    }

    public function test_milestone_proof_upload_request_and_withdraw(): void
    {
        $milestone = $this->activeJob()->milestones()->firstOrFail();

        $this->post(route('milestones.start', $milestone))->assertRedirect(route('milestones.show', $milestone));
        $this->post(route('milestones.payment-requests.store', $milestone))->assertSessionHasErrors('rule');

        $photo = UploadedFile::fake()->image('site.jpg', 1200, 900);
        $this->post(route('milestones.proofs.store', $milestone), ['files' => [$photo], 'caption' => 'Cabinets in'])
            ->assertSessionHasNoErrors();
        $this->post(route('milestones.notes.store', $milestone), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('milestones.notes.store', $milestone), ['note' => 'Wiring done']);

        $proof = $milestone->proofs()->where('type', 'photo')->firstOrFail();
        $this->assertSame(hash('sha256', (string) Storage::get((string) $proof->path)), $proof->sha256);
        $this->assertNotNull($proof->captured_at);

        $this->post(route('milestones.payment-requests.store', $milestone), ['note' => 'Please review'])->assertSessionHasNoErrors();
        $this->assertSame('requested', $milestone->refresh()->status);
        $this->delete(route('proofs.destroy', $proof))->assertSessionHasErrors('rule');

        $this->get(route('milestones.show', $milestone))->assertInertia(fn (Assert $page) => $page
            ->component('milestones/show')
            ->where('milestone.proofs_locked', true)
            ->has('proofs', 2)
            ->has('requests', 1));

        $this->post(route('payment-requests.withdraw', $milestone->openRequest()->firstOrFail()));
        $this->assertSame('in_progress', $milestone->refresh()->status);
        $this->delete(route('proofs.destroy', $proof))->assertSessionHasNoErrors();
        $this->assertFalse(Storage::exists((string) $proof->path));
        $this->assertFalse(Storage::exists((string) $proof->thumb_path));
    }

    public function test_variation_orders_and_payments_from_the_job_page(): void
    {
        $job = $this->activeJob();
        $handover = $job->milestones()->where('name', 'Handover')->firstOrFail();

        $this->post(route('jobs.variation-orders.store', $job), ['kind' => 'deduction', 'amount' => '500', 'description' => 'Skip backsplash'])
            ->assertSessionHasErrors(['milestone_id' => 'Choose the milestone this deduction comes off.']);
        $this->post(route('jobs.variation-orders.store', $job), [
            'kind' => 'deduction', 'amount' => '500', 'description' => 'Skip backsplash', 'milestone_id' => $handover->id,
            'attachments' => [UploadedFile::fake()->image('drawing.png')],
        ])->assertSessionHasNoErrors();

        $vo = $job->variationOrders()->firstOrFail();
        $this->assertSame(-50_000, $vo->amount_cents);
        $this->assertCount(1, $vo->attachments);
        $this->post(route('variation-orders.send', $vo));
        $this->assertSame('sent', $vo->refresh()->status);

        $this->post(route('jobs.payments.store', $job), ['amount' => '3000', 'paid_on' => now()->addDay()->toDateString(), 'method' => 'transfer'])
            ->assertSessionHasErrors(['paid_on' => 'The payment date field must be a date before or equal to today.']);
        $this->post(route('jobs.payments.store', $job), [
            'amount' => '3000.00', 'paid_on' => today()->toDateString(), 'method' => 'transfer', 'reference' => 'MBB 1',
            'milestone_id' => Milestone::where('job_id', $job->id)->value('id'),
        ])->assertRedirect(route('jobs.show', $job));
        $this->assertSame(300_000, $job->payments()->value('amount_cents'));
    }

    public function test_company_settings_update_bank_details(): void
    {
        $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page->component('settings/company'));
        $this->post(route('company.update'), ['name' => ''])->assertSessionHasErrors(['name' => 'The name field is required.']);

        $this->post(route('company.update'), [
            'name' => 'Ali Renovation', 'bank_name' => 'Maybank', 'bank_account_name' => 'Ali Renovation', 'bank_account_no' => '5140',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect(route('company.edit'));

        $company = $this->owner->company->refresh();
        $this->assertSame('Maybank', $company->bank_name);
        $this->assertTrue(Storage::exists((string) $company->logo_path));
    }

    public function test_contractor_comments_are_scoped_to_own_company(): void
    {
        $job = $this->activeJob();
        $vo = app(JobFlow::class)->createVariationOrder($job, $this->owner, ['description' => 'Extra', 'amount_cents' => 1000]);

        $this->post(route('comments.store'), ['commentable_type' => 'variation_order', 'commentable_id' => $vo->id, 'body' => 'Drawing attached'])
            ->assertSessionHasNoErrors();
        $this->get(route('jobs.show', $job))->assertInertia(fn (Assert $page) => $page
            ->where('variationOrders.0.comments.0.body', 'Drawing attached'));

        $this->actingAs(User::factory()->for(Company::factory())->create())
            ->post(route('comments.store'), ['commentable_type' => 'variation_order', 'commentable_id' => $vo->id, 'body' => 'Hi'])
            ->assertNotFound();
    }

    public function test_photo_proof_gets_a_separate_thumbnail_and_original_is_untouched(): void
    {
        $milestone = $this->activeJob()->milestones()->firstOrFail();
        $this->post(route('milestones.start', $milestone));

        $this->post(route('milestones.proofs.store', $milestone), ['files' => [UploadedFile::fake()->image('site.jpg', 2000, 1500)]])
            ->assertSessionHasNoErrors();

        $proof = $milestone->proofs()->firstOrFail();
        $this->assertNotNull($proof->thumb_path);
        $this->assertNotSame($proof->path, $proof->thumb_path);
        [$width, $height] = (array) getimagesizefromstring((string) Storage::get((string) $proof->thumb_path));
        $this->assertSame([640, 480], [$width, $height]);
        $this->assertSame($proof->sha256, hash('sha256', (string) Storage::get((string) $proof->path)));
    }

    public function test_cancelling_withdraws_open_requests_blocks_work_and_frees_the_plan_slot(): void
    {
        $job = $this->activeJob();
        $milestone = $job->milestones()->firstOrFail();
        $this->post(route('milestones.start', $milestone));
        $this->post(route('milestones.notes.store', $milestone), ['note' => 'Done']);
        $this->post(route('milestones.payment-requests.store', $milestone));
        Notification::fake();

        $this->post(route('jobs.cancel', $job), ['reason' => ''])->assertSessionHasErrors(['reason' => 'The reason field is required.']);
        $this->post(route('jobs.cancel', $job), ['reason' => 'Client sold the house'])->assertRedirect(route('jobs.show', $job));

        $this->assertSame('cancelled', $job->refresh()->status);
        $this->assertSame('withdrawn', $milestone->paymentRequests()->value('status'));
        $this->assertSame('in_progress', $milestone->refresh()->status);
        Notification::assertSentTo($job->client, ClientAlert::class, fn (ClientAlert $a) => str_contains($a->text(), 'Client sold the house'));

        $this->post(route('milestones.notes.store', $milestone), ['note' => 'More'])->assertSessionHasErrors('rule');
        $this->post(route('milestones.payment-requests.store', $milestone))->assertSessionHasErrors('rule');
        $this->post(route('jobs.cancel', $job), ['reason' => 'again'])->assertSessionHasErrors('rule');
        $this->get(route('jobs.show', $job))->assertInertia(fn (Assert $page) => $page
            ->where('job.cancel_reason', 'Client sold the house')
            ->where('job.share_url', null));

        // Free plan allows one open job; the cancelled one no longer counts.
        $this->post(route('jobs.send', $this->storeJob()))->assertSessionHasNoErrors();
    }

    public function test_a_cancelled_draft_was_never_shared_so_its_link_stays_closed(): void
    {
        $job = $this->storeJob();
        $this->post(route('jobs.cancel', $job), ['reason' => 'Duplicate']);
        Auth::logout();

        $this->get('/c/'.$job->share_token)->assertNotFound();
    }

    public function test_uploads_over_the_plan_storage_are_refused_before_anything_is_kept(): void
    {
        $job = $this->activeJob();
        $milestone = $job->milestones()->firstOrFail();
        $this->post(route('milestones.start', $milestone));
        Proof::create(['milestone_id' => $milestone->id, 'uploaded_by' => $this->owner->id, 'type' => 'file', 'path' => 'old.pdf', 'bytes' => 5 * 1024 ** 3 - 10]);

        $this->post(route('milestones.proofs.store', $milestone), ['files' => [UploadedFile::fake()->image('site.jpg', 400, 300)]])
            ->assertSessionHasErrors(['rule' => 'Your Free plan includes 5 GB of photo storage and it is full. Upgrade or remove unused proof to add more.']);
        $this->assertSame(1, $milestone->proofs()->count());
        $this->assertSame([], Storage::allFiles("proofs/{$job->company_id}/{$milestone->id}"));

        $this->post(route('jobs.variation-orders.store', $job), [
            'kind' => 'extra', 'amount' => '100', 'description' => 'Extra', 'attachments' => [UploadedFile::fake()->image('d.png', 400, 300)],
        ])->assertSessionHasErrors('rule');
        $this->assertSame(0, $job->variationOrders()->count());

        $this->post(route('milestones.notes.store', $milestone), ['note' => 'Notes take no storage'])->assertSessionHasNoErrors();

        $this->owner->company->forceFill(['plan' => 'solo'])->save();
        $this->post(route('milestones.proofs.store', $milestone), ['files' => [UploadedFile::fake()->image('site.jpg', 400, 300)]])
            ->assertSessionHasNoErrors();
        $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('company.storage_limit_bytes', 50 * 1024 ** 3)
            ->where('company.storage_used_bytes', fn (int $used) => $used > 5 * 1024 ** 3 - 10));
    }

    public function test_dashboard_and_job_page_show_the_latest_photos_of_own_company_only(): void
    {
        $milestone = $this->activeJob()->milestones()->firstOrFail();
        $this->post(route('milestones.start', $milestone));
        $this->post(route('milestones.proofs.store', $milestone), ['files' => [UploadedFile::fake()->image('site.jpg', 800, 600)], 'caption' => 'Footings']);
        $this->post(route('milestones.notes.store', $milestone), ['note' => 'Notes are not photos']);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('photos', 1)
            ->where('photos.0.caption', 'Footings')
            ->where('photos.0.milestone_id', $milestone->id)
            ->where('photos.0.job', 'Kitchen renovation'));
        $this->get(route('jobs.show', $milestone->job_id))->assertInertia(fn (Assert $page) => $page->has('photos', 1));

        $this->actingAs(User::factory()->for(Company::factory())->create())
            ->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('photos', 0));
    }
}
