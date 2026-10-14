<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Models\Client;
use App\Models\User;
use App\Services\JobFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_contractor_choice_is_saved_and_translates_the_interface(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('dashboard'))->post(route('locale.update'), ['locale' => 'ms'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame('ms', $user->refresh()->locale);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'ms')
            ->where('translations.Dashboard', __('Dashboard', [], 'ms'))
            ->has('locales', 3));

        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_guests_get_their_browser_language_until_they_choose(): void
    {
        $this->get('/', ['Accept-Language' => 'zh-CN,zh;q=0.9'])->assertInertia(fn (Assert $page) => $page->where('locale', 'zh'));
        $this->get('/', ['Accept-Language' => 'de-DE'])->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

        $this->post(route('locale.update'), ['locale' => 'ms']);
        $this->get('/', ['Accept-Language' => 'zh-CN'])->assertInertia(fn (Assert $page) => $page->where('locale', 'ms'));
    }

    public function test_client_texts_arrive_in_the_clients_language(): void
    {
        $sent = [];
        $this->app->instance(SmsSender::class, new class($sent) implements SmsSender
        {
            /** @param list<string> $sent */
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): void
            {
                $this->sent[] = $message;
            }
        });
        $owner = User::factory()->create(['locale' => 'en']);
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Puan Siti', 'phone' => '0123456789', 'locale' => 'ms']);
        $flow = app(JobFlow::class);
        $job = $flow->createJob($owner, ['company_id' => $owner->company_id, 'client_id' => $client->id, 'title' => 'Dapur', 'site_address' => 'Shah Alam', 'quote_cents' => 100], [['name' => 'Deposit', 'amount_cents' => 100]]);

        $flow->sendJob($job, $owner);

        $expected = __('sent you “:job” to review and accept.', ['job' => 'Dapur'], 'ms');
        $this->assertNotSame(__('sent you “:job” to review and accept.', ['job' => 'Dapur'], 'en'), $expected, 'Malay translation missing');
        $this->assertStringContainsString($expected, end($sent));
        $this->assertSame('en', app()->getLocale(), 'the contractor\'s request stays in English');
    }

    public function test_every_interface_string_is_translated_into_malay_and_mandarin(): void
    {
        exec(PHP_BINARY.' '.base_path('scripts/i18n-keys.php').' --missing', $output, $exit);

        $this->assertSame(0, $exit, "Untranslated keys:\n".implode("\n", $output));
    }

    public function test_the_job_record_pdf_embeds_a_chinese_font_only_for_chinese(): void
    {
        $owner = User::factory()->create();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Tan', 'phone' => '0123456789']);
        $job = app(JobFlow::class)->createJob($owner, ['company_id' => $owner->company_id, 'client_id' => $client->id, 'title' => 'Roof', 'site_address' => 'KL', 'quote_cents' => 100], [['name' => 'Deposit', 'amount_cents' => 100]]);
        $this->actingAs($owner);

        $this->post(route('locale.update'), ['locale' => 'zh']);
        $chinese = $this->get(route('jobs.record', $job))->assertOk()->getContent();
        $this->assertStringContainsString('NotoSansSC', (string) $chinese);

        $this->post(route('locale.update'), ['locale' => 'en']);
        $english = $this->get(route('jobs.record', $job))->assertOk()->getContent();
        $this->assertStringNotContainsString('NotoSansSC', (string) $english);
    }
}
