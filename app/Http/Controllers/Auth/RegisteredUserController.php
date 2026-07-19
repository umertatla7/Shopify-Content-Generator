<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AccountProvisioningService;
use App\Services\Shopify\ShopifyService;
use App\Support\ShopifyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request, ShopifyService $shopify, ShopifyContext $shopifyContext): Response|RedirectResponse
    {
        if ($redirect = $this->shopifyAppRedirect($request, $shopify, $shopifyContext)) {
            return $redirect;
        }

        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, AccountProvisioningService $accounts, ShopifyContext $shopifyContext): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $account = $accounts->createForUser($user, $validated['account_name']);

        ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'subject_type' => $account->getMorphClass(),
            'subject_id' => $account->id,
            'action' => 'account.created',
            'description' => "{$user->name} created {$account->name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->to($shopifyContext->decorate(route('dashboard'), $request));
    }

    private function shopifyAppRedirect(
        Request $request,
        ShopifyService $shopify,
        ShopifyContext $shopifyContext,
    ): ?RedirectResponse {
        if ((bool) config('services.shopify.manual_connection_mode', false)) {
            return null;
        }

        if (! filled(config('services.shopify.public_app_api_key'))) {
            return null;
        }

        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if ($shop === '' || ! $shopify->isValidShopDomain($shop)) {
            return null;
        }

        return redirect()->to($shopifyContext->decorate(route('shopify.app', ['shop' => $shop]), $request));
    }
}
