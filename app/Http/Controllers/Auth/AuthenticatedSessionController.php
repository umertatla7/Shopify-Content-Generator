<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Shopify\ShopifyService;
use App\Support\ShopifyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, ShopifyService $shopify, ShopifyContext $shopifyContext): Response|RedirectResponse
    {
        if ($redirect = $this->shopifyAppRedirect($request, $shopify, $shopifyContext)) {
            return $redirect;
        }

        return Inertia::render('Auth/Login');
    }

    public function store(Request $request, ShopifyContext $shopifyContext): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
        }

        $request->session()->regenerate();

        ActivityLog::query()->create([
            'account_id' => $request->user()->current_account_id,
            'user_id' => $request->user()->id,
            'subject_type' => $request->user()->getMorphClass(),
            'subject_id' => $request->user()->id,
            'action' => 'auth.login',
            'description' => "{$request->user()->name} signed in.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended($shopifyContext->decorate(route('dashboard'), $request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            ActivityLog::query()->create([
                'account_id' => $user->current_account_id,
                'user_id' => $user->id,
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'action' => 'auth.logout',
                'description' => "{$user->name} signed out.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
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
