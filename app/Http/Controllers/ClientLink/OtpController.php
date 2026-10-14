<?php

namespace App\Http\Controllers\ClientLink;

use App\Contracts\SmsSender;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveClientLink;
use App\Models\ClientSession;
use App\Models\Job;
use App\Services\JobFlow;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Phone OTP that proves the person opening the link is the job's client. */
class OtpController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const SENDS_PER_WINDOW = 3;

    public function send(Request $request, SmsSender $sms): RedirectResponse
    {
        $job = $this->job($request);
        $key = "client-otp:{$job->id}";

        if (RateLimiter::tooManyAttempts($key, self::SENDS_PER_WINDOW)) {
            throw ValidationException::withMessages([
                'code' => __('Too many codes requested. Try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }
        RateLimiter::hit($key, 15 * 60);

        $code = (string) random_int(100000, 999999);
        $pending = ClientSession::create([
            'client_id' => $job->client_id,
            'job_id' => $job->id,
            'phone' => $job->client->phone,
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes((int) config('jobproof.otp_ttl_minutes')),
        ]);
        $request->session()->put($this->pendingKey($job), $pending->id);

        try {
            $sms->send($job->client->phone, __(':code is your :app code for ":title". It expires in :minutes minutes.', [
                'code' => $code,
                'app' => config('app.name'),
                'title' => $job->title,
                'minutes' => config('jobproof.otp_ttl_minutes'),
            ]));
        } catch (RequestException|ConnectionException $e) {
            report($e);
            $pending->delete();
            throw ValidationException::withMessages(['code' => __('We couldn’t send the code just now. Please try again in a minute.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Code sent to your phone.')]);

        return to_route('client.show', $job->share_token);
    }

    public function verify(Request $request, JobFlow $flow): RedirectResponse
    {
        $job = $this->job($request);
        $request->validate(['code' => ['required', 'digits:6']]);

        $pending = ClientSession::where('job_id', $job->id)->whereNull('verified_at')
            ->whereKey((int) $request->session()->get($this->pendingKey($job)))
            ->first();

        if (! $pending || ! $pending->otp_expires_at || $pending->otp_expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => __('This code has expired. Request a new one.')]);
        }
        if ($pending->otp_attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => __('Too many wrong attempts. Request a new code.')]);
        }
        if (! Hash::check((string) $request->input('code'), (string) $pending->otp_hash)) {
            $pending->increment('otp_attempts');
            throw ValidationException::withMessages(['code' => __('That code is incorrect.')]);
        }

        $deviceToken = Str::random(64);
        $pending->update(['verified_at' => now(), 'otp_hash' => null, 'device_token' => hash('sha256', $deviceToken)]);
        $request->session()->forget($this->pendingKey($job));
        $flow->log($job, $job->client, 'client.verified', $pending);

        $days = (int) config('jobproof.client_session_days');
        // Scoped to this job's link path, so each job keeps its own device session.
        $path = (string) parse_url(route('client.show', $job->share_token), PHP_URL_PATH);

        return to_route('client.show', $job->share_token)
            ->withCookie(cookie(ResolveClientLink::COOKIE, $deviceToken, $days * 24 * 60, $path));
    }

    private function job(Request $request): Job
    {
        return $request->attributes->get('job');
    }

    private function pendingKey(Job $job): string
    {
        return "client_otp.{$job->id}";
    }
}
