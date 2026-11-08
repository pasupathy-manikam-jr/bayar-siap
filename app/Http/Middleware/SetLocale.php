<?php

namespace App\Http\Middleware;

use App\Support\Translations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language: the session's choice, else the signed-in contractor's saved one, else the browser's (or
 * phone's Accept-Language) preference, else English. API requests have no session: their user comes from the
 * bearer token. (Client-link pages refine this with the client's choice.)
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = Translations::locales();
        $chosen = $request->hasSession()
            ? $request->session()->get('locale') ?? $request->user()?->locale
            : $request->user('sanctum')?->locale;

        app()->setLocale(in_array($chosen, $supported, true) ? $chosen : ($request->getPreferredLanguage($supported) ?? 'en'));

        return $next($request);
    }
}
