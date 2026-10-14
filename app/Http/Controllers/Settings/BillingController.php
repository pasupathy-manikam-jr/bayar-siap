<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Upgrade to Solo through Stripe Checkout; manage or cancel through Stripe's billing portal. */
class BillingController extends Controller
{
    public function checkout(Request $request): Response
    {
        $company = $request->user()->company;
        if (! Company::billingConfigured()) {
            return $this->notReady();
        }
        if ($company->subscribed()) {
            return to_route('company.edit');
        }

        return Inertia::location($company->soloCheckoutUrl(route('billing.success'), route('company.edit')));
    }

    /** Stripe sends the browser back here; sync now rather than wait for the webhook. */
    public function success(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $company->syncPlanFromSubscription();
        Inertia::flash('toast', $company->plan === Company::PAID_PLAN
            ? ['type' => 'success', 'message' => __('You’re on the Solo plan. Thank you!')]
            : ['type' => 'info', 'message' => __('Payment received. Your plan updates in a moment.')]);

        return to_route('company.edit');
    }

    public function portal(Request $request): Response
    {
        $company = $request->user()->company;
        if (! Company::billingConfigured() || ! $company->hasStripeId()) {
            return $this->notReady();
        }

        return Inertia::location($company->billingPortalUrl(route('company.edit')));
    }

    private function notReady(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __('Online billing isn’t set up yet. Contact us to upgrade.')]);

        return to_route('company.edit');
    }
}
