<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Services\Shopify\ShopifyService;
use App\Support\ShopifyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ShopifyInstallController extends Controller
{
    public function app(Request $request, ShopifyService $shopify, ShopifyContext $shopifyContext): RedirectResponse
    {
        $shopifyContext->remember($request);

        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if ($shop === '' || ! $shopify->isValidShopDomain($shop)) {
            return redirect()->route('login')->withErrors([
                'shopify' => 'A valid Shopify shop domain is required.',
            ]);
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->guest($request->fullUrl());
        }

        $store = ShopifyStore::query()
            ->forAccount($user->current_account_id)
            ->where('shop_domain', $shop)
            ->first();

        if ($store?->credential?->admin_api_access_token) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))
                ->with('status', "{$store->name} is already connected.");
        }

        return redirect()->to($shopifyContext->decorate(route('shopify.install.start', ['shop' => $shop]), $request));
    }

    public function start(Request $request, ShopifyService $shopify, ShopifyContext $shopifyContext): RedirectResponse
    {
        $this->authorize('create', ShopifyStore::class);

        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if ($shop === '' || ! $shopify->isValidShopDomain($shop)) {
            throw ValidationException::withMessages([
                'shop' => 'Enter a valid myshopify.com store domain.',
            ]);
        }

        $this->ensureStoreSlotAvailable($request, $shop);

        $state = Str::random(40);

        $request->session()->put('shopify_oauth', [
            'state' => $state,
            'shop' => $shop,
            'account_id' => $request->user()->current_account_id,
            'user_id' => $request->user()->id,
            'started_at' => now()->toIso8601String(),
        ]);

        return redirect()->away($shopify->authorizationUrl($shop, $state));
    }

    public function callback(Request $request, ShopifyService $shopify, ShopifyContext $shopifyContext): RedirectResponse
    {
        $oauth = $request->session()->pull('shopify_oauth');

        if (! $oauth) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => 'The Shopify install session expired. Start the install again.',
            ]);
        }

        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if (! $shopify->isValidShopDomain($shop)) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => 'Shopify returned an invalid store domain.',
            ]);
        }

        if ($shop !== ($oauth['shop'] ?? null)) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => 'The Shopify callback shop did not match the install request.',
            ]);
        }

        if ((string) $request->query('state', '') !== (string) ($oauth['state'] ?? '')) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => 'The Shopify install state did not match. Please try again.',
            ]);
        }

        if (! $shopify->verifyRequestHmac($request->query(), $request->query('hmac'))) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => 'Shopify callback verification failed.',
            ]);
        }

        if ((int) ($oauth['account_id'] ?? 0) !== (int) $request->user()->current_account_id) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => 'The active account changed during install. Switch back and retry.',
            ]);
        }

        try {
            $tokenPayload = $shopify->exchangeAuthorizationCode($shop, (string) $request->query('code'));
            $metadata = $shopify->shopDetailsFromAccessToken($shop, $tokenPayload['access_token']);
            $store = $this->upsertInstalledStore($request, $shop, $metadata, $tokenPayload);
        } catch (RuntimeException $exception) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => $exception->getMessage(),
            ]);
        }

        ActivityLog::query()->create([
            'account_id' => $store->account_id,
            'user_id' => $request->user()->id,
            'subject_type' => $store->getMorphClass(),
            'subject_id' => $store->id,
            'action' => 'shopify_store.oauth_connected',
            'description' => "Shopify OAuth install completed for {$store->name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))
            ->with('status', "Shopify connected for {$store->name}. You can sync products now.");
    }

    private function upsertInstalledStore(Request $request, string $shop, array $metadata, array $tokenPayload): ShopifyStore
    {
        $store = ShopifyStore::query()->updateOrCreate(
            [
                'account_id' => $request->user()->current_account_id,
                'shop_domain' => $shop,
            ],
            [
                'connected_by' => $request->user()->id,
                'name' => $metadata['name'] ?? Str::before($shop, '.'),
                'shop_url' => 'https://'.$shop,
                'country' => $metadata['shopAddress']['countryCode'] ?? null,
                'default_language' => $metadata['primaryLocale'] ?? 'en',
                'status' => 'connected',
                'metadata' => $metadata,
                'last_validated_at' => now(),
                'validation_error' => null,
            ]
        );

        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) ($tokenPayload['scope'] ?? '')))));

        $store->credential()->updateOrCreate(
            ['shopify_store_id' => $store->id],
            [
                'account_id' => $store->account_id,
                'admin_api_access_token' => $tokenPayload['access_token'],
                'api_key' => config('services.shopify.public_app_api_key'),
                'client_secret' => config('services.shopify.public_app_client_secret'),
                'scopes' => $scopes ?: null,
                'expires_at' => null,
            ]
        );

        return $store->fresh('credential');
    }

    private function ensureStoreSlotAvailable(Request $request, string $shop): void
    {
        $existingStore = ShopifyStore::query()
            ->where('account_id', $request->user()->current_account_id)
            ->where('shop_domain', $shop)
            ->first();

        if ($existingStore) {
            return;
        }

        $account = $request->user()->currentAccount;
        $plan = $account ? Plan::query()->where('key', $account->plan_key)->first() : null;
        $storeLimit = (int) ($plan?->store_limit ?? 1);
        $storeCount = ShopifyStore::forAccount($request->user()->current_account_id)->count();

        if ($storeCount >= $storeLimit) {
            throw ValidationException::withMessages([
                'shop' => "Your {$account?->plan_key} plan allows {$storeLimit} connected store".($storeLimit === 1 ? '' : 's').'. Upgrade before connecting another store.',
            ]);
        }
    }
}
