<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\JobFlow;
use App\Services\LogSmsSender;
use App\Services\TwilioSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TwilioSmsSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_driver_by_default_and_twilio_when_configured(): void
    {
        $this->assertInstanceOf(LogSmsSender::class, app(SmsSender::class));

        config(['services.sms.driver' => 'twilio', 'services.twilio' => ['sid' => 'AC123', 'token' => 'secret', 'from' => '+14155238886', 'channel' => 'whatsapp']]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        app(SmsSender::class)->send('012-345 6789', '123456 is your JobProof code');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('AC123:secret'))
            && $r['To'] === 'whatsapp:+60123456789'
            && $r['From'] === 'whatsapp:+14155238886'
            && $r['Body'] === '123456 is your JobProof code');
    }

    public function test_phone_numbers_are_normalised_to_e164(): void
    {
        $this->assertSame('+60123456789', TwilioSmsSender::e164('012-345 6789'));
        $this->assertSame('+60123456789', TwilioSmsSender::e164('+60 12-345 6789'));
        $this->assertSame('+6591234567', TwilioSmsSender::e164('+65 9123 4567'));
    }

    public function test_otp_shows_a_message_when_the_gateway_fails(): void
    {
        $this->withoutExceptionHandling([ValidationException::class]);
        config(['services.sms.driver' => 'twilio', 'services.twilio' => ['sid' => 'AC1', 'token' => 'x', 'from' => '+1', 'channel' => 'sms']]);
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'down'], 503)]);
        $owner = User::factory()->create();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Siti', 'phone' => '0123456789']);
        $flow = app(JobFlow::class);
        $job = $flow->createJob($owner, ['company_id' => $owner->company_id, 'client_id' => $client->id, 'title' => 'T', 'site_address' => 'A', 'quote_cents' => 100], [['name' => 'M', 'amount_cents' => 100]]);
        $job->forceFill(['status' => 'sent'])->save();
        ActivityLog::create(['company_id' => $job->company_id, 'job_id' => $job->id, 'action' => 'job.sent']);

        $this->post('/c/'.$job->share_token.'/otp/send')
            ->assertSessionHasErrors(['code' => 'We couldn’t send the code just now. Please try again in a minute.']);
        $this->assertSame(0, ClientSession::count());
    }
}
