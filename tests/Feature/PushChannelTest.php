<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ContractorAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_through_fcm_v1_and_forgets_unregistered_tokens(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $file = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($file, json_encode(['project_id' => 'jobproof-test', 'client_email' => 'push@jobproof-test.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.fcm.credentials' => $file]);

        $user = User::factory()->create();
        $user->deviceTokens()->create(['platform' => 'android', 'token' => 'live-token']);
        $user->deviceTokens()->create(['platform' => 'android', 'token' => 'stale-token']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => fn (Request $request) => $request['message']['token'] === 'stale-token'
                ? Http::response(['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                : Http::response(['name' => 'projects/jobproof-test/messages/1']),
        ]);

        $user->notify(new ContractorAlert('Payment request approved', 'Puan Siti approved RM 100.00.', [], 'https://example.com/m/1', 7));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com')
            && $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && count(explode('.', $r['assertion'])) === 3);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://fcm.googleapis.com/v1/projects/jobproof-test/messages:send'
            && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && $r['message']['notification']['title'] === 'Payment request approved'
            && $r['message']['data']['job_id'] === '7');
        $this->assertSame(['live-token'], $user->deviceTokens()->pluck('token')->all());

        unlink($file);
    }
}
