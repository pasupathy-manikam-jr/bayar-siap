<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\ContractorAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Push to the contractor's registered devices through FCM HTTP v1 (free). Without FCM_CREDENTIALS the message
 * is logged instead, which is how local dev and the pre-Android period run.
 */
class PushChannel
{
    public function send(User $notifiable, ContractorAlert $notification): void
    {
        $credentials = $this->credentials();

        foreach ($notifiable->deviceTokens as $device) {
            /** @var DeviceToken $device */
            if ($credentials === null) {
                Log::info("Push to {$device->platform} …".substr($device->token, -6).": {$notification->translatedTitle()} — {$notification->translatedBody()}");

                continue;
            }

            $response = Http::withToken($this->accessToken($credentials))
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                    'message' => [
                        'token' => $device->token,
                        'notification' => ['title' => $notification->translatedTitle(), 'body' => $notification->translatedBody()],
                        'data' => ['url' => $notification->url, 'job_id' => (string) $notification->jobId],
                    ],
                ]);

            // The app was uninstalled or the token rotated: forget it so we stop sending.
            if ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
                $device->delete();
            } elseif ($response->failed()) {
                $response->throw();
            }
        }
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null */
    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');
        if (! is_string($path) || $path === '') {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json) || ! isset($json['project_id'], $json['client_email'], $json['private_key'])) {
            throw new RuntimeException("FCM_CREDENTIALS ({$path}) is not a Firebase service-account JSON file.");
        }

        return [
            'project_id' => (string) $json['project_id'],
            'client_email' => (string) $json['client_email'],
            'private_key' => (string) $json['private_key'],
            'token_uri' => (string) ($json['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
        ];
    }

    /**
     * Google OAuth access token from a self-signed service-account JWT; cached for most of its hour.
     *
     * @param  array{project_id: string, client_email: string, private_key: string, token_uri: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm-access-token:'.$credentials['client_email'], now()->addMinutes(50), function () use ($credentials) {
            $b64 = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            $now = time();
            $unsigned = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the FCM token request; check the private key.');
            }

            return (string) Http::asForm()->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ])->throw()->json('access_token');
        });
    }
}
