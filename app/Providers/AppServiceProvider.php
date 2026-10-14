<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Models\Company;
use App\Services\LogSmsSender;
use App\Services\TwilioSmsSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => config('services.sms.driver') === 'twilio'
            ? new TwilioSmsSender
            : new LogSmsSender);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The company (tenant) is the Stripe customer, not the user.
        Cashier::useCustomerModel(Company::class);
        Event::listen(function (WebhookHandled $event) {
            $customer = $event->payload['data']['object']['customer'] ?? null;
            if (str_starts_with((string) ($event->payload['type'] ?? ''), 'customer.subscription.') && is_string($customer)) {
                Company::where('stripe_id', $customer)->first()?->syncPlanFromSubscription();
            }
        });

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
