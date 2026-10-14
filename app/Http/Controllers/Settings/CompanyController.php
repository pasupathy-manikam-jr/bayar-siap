<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Company profile and the bank details clients see on payment requests. */
class CompanyController extends Controller
{
    public function edit(Request $request): Response
    {
        $company = $request->user()->company;

        return Inertia::render('settings/company', [
            'company' => [
                ...$company->only(['name', 'bank_name', 'bank_account_name', 'bank_account_no', 'plan']),
                'storage_used_bytes' => $company->storageUsedBytes(),
                'storage_limit_bytes' => $company->storageLimitBytes(),
                'active_job_limit' => $company->activeJobLimit(),
                'has_billing_account' => $company->hasStripeId(),
                'subscription_ends_at' => $company->subscription()?->ends_at?->toIso8601String(),
                'logo_url' => $company->logo_path ? Storage::temporaryUrl($company->logo_path, now()->addHour()) : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ], [], ['bank_account_no' => __('account number')]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')?->store("logos/{$company->id}");
        }
        unset($data['logo']);
        $company->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company updated.')]);

        return to_route('company.edit');
    }
}
