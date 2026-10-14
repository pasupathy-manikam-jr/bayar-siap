<?php

namespace App\Http\Middleware;

use App\Support\Translations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language: the session's choice, else the signed-in contractor's saved one, else the browser's
 * preferred language when we have it, else English. (Client-link pages refine this with the client's choice.)
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = Translations::locales();
        $chosen = $request->session()->get('locale') ?? $request->user()?->locale;

        app()->setLocale(in_array($chosen, $supported, true) ? $chosen : ($request->getPreferredLanguage($supported) ?? 'en'));

        return $next($request);
    }
}
