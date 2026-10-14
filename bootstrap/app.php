<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveClientLink;
use App\Http\Middleware\SetLocale;
use App\Support\Translations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias(['client.link' => ResolveClientLink::class]);

        // Stripe posts webhooks without a CSRF token; Cashier verifies its signature instead.
        $middleware->validateCsrfTokens(except: ['stripe/*']);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded error pages instead of Laravel's plain white ones. A 500 keeps the debug page locally.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            $branded = in_array($status, [403, 404, 419, 503], true)
                || ($status === 500 && ! app()->environment(['local', 'testing']));

            if (! $branded || $request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            // Unknown URLs never reach the web middleware: pick the language here and pass its strings along.
            $locale = app()->getLocale() !== config('app.locale') ? app()->getLocale()
                : ($request->getPreferredLanguage(Translations::locales()) ?? 'en');
            app()->setLocale($locale);

            return Inertia::render('error', [
                'status' => $status,
                'locale' => $locale,
                'locales' => config('jobproof.locales'),
                'translations' => Translations::for($locale),
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
