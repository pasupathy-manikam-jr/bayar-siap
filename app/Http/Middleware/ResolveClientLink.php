<?php

namespace App\Http\Middleware;

use App\Models\ClientSession;
use App\Models\Job;
use App\Support\Translations;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the job behind a client link (/c/{shareToken}) and the client's OTP session on this device.
 * Usage: `client.link` requires a verified session; `client.link:optional` lets the OTP pages through without one.
 */
class ResolveClientLink
{
    public const COOKIE = 'jobproof_client';

    public function handle(Request $request, Closure $next, string $mode = 'verified'): Response
    {
        // No contractor user on client pages, so the company scope never hides the client's own job.
        Auth::shouldUse('client-link');

        $shareToken = (string) $request->route('shareToken');
        $request->route()?->forgetParameter('shareToken');

        $job = Job::where('share_token', $shareToken)->whereIn('status', ['sent', 'active', 'completed', 'cancelled'])->firstOrFail();
        // A draft cancelled before it was ever sent was never shared; keep its link closed.
        abort_if($job->status === 'cancelled' && ! $job->activity()->where('action', 'job.sent')->exists(), 404);
        $session = $this->verifiedSession($request, $job);

        if ($mode !== 'optional' && ! $session) {
            return to_route('client.show', $job->share_token);
        }

        $clientLocale = $session?->client->locale;
        if (! $request->session()->has('locale') && in_array($clientLocale, Translations::locales(), true)) {
            app()->setLocale($clientLocale);
        }

        $request->attributes->set('job', $job);
        $request->attributes->set('clientSession', $session);

        return $next($request);
    }

    private function verifiedSession(Request $request, Job $job): ?ClientSession
    {
        $token = $request->cookie(self::COOKIE);
        if (! is_string($token) || $token === '') {
            return null;
        }

        return ClientSession::with('client')
            ->where('job_id', $job->id)
            ->where('device_token', hash('sha256', $token))
            ->where('verified_at', '>=', now()->subDays((int) config('jobproof.client_session_days')))
            ->first();
    }
}
