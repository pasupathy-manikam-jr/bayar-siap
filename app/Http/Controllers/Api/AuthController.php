<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = DB::transaction(function () use ($data) {
            $company = Company::create(['name' => $data['company_name'], 'plan' => 'free']);

            return $company->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => 'owner',
            ]);
        });

        return response()->json($this->tokenResponse($user, $data['device_name'] ?? 'app'), 201);
    }

    /** @return array<string, mixed> */
    public function login(Request $request): array
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return $this->tokenResponse($user, $data['device_name'] ?? 'app');
    }

    /** Emails the reset link; the link opens the web reset page. Same answer whether or not the email exists. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __('If that email has an account, a reset link is on its way.')]);
    }

    /** @return array<string, mixed> */
    public function notifications(Request $request): array
    {
        $user = $request->user();
        $page = $user->notifications()->paginate(30);
        $user->unreadNotifications()->whereIn('id', $page->getCollection()->pluck('id'))->update(['read_at' => now()]);

        return [
            'data' => $page->getCollection()->map(fn (DatabaseNotification $n) => ['id' => $n->id, ...$n->data, 'title' => __((string) ($n->data['title'] ?? ''), (array) ($n->data['replace'] ?? [])), 'body' => __((string) ($n->data['body'] ?? ''), (array) ($n->data['replace'] ?? [])), 'unread' => $n->read_at === null, 'created_at' => $n->created_at?->toIso8601String()]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ];
    }

    /** Stripe Checkout / billing portal URL for the app to open in a browser. */
    public function billing(Request $request, string $action): JsonResponse
    {
        $company = $request->user()->company;
        if (! Company::billingConfigured()) {
            return response()->json(['message' => __('Online billing isn’t set up yet. Contact us to upgrade.')], 503);
        }
        $url = match (true) {
            $action === 'checkout' && ! $company->subscribed() => $company->soloCheckoutUrl(route('billing.success'), route('company.edit')),
            $company->hasStripeId() => $company->billingPortalUrl(route('company.edit')),
            default => abort(422, __('No billing account yet; upgrade first.')),
        };

        return response()->json(['url' => $url]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): User
    {
        return $request->user()->load('company')->setAttribute('unread_notifications', $request->user()->unreadNotifications()->count());
    }

    public function updateCompany(Request $request): Company
    {
        $company = $request->user()->company;
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:50',
            'logo' => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store("logos/{$company->id}");
        }
        unset($data['logo']);
        $company->update($data);

        return $company;
    }

    /** @return array{token: string, user: User} */
    private function tokenResponse(User $user, string $device): array
    {
        return [
            'token' => $user->createToken($device)->plainTextToken,
            'user' => $user->load('company'),
        ];
    }
}
