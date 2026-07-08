<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Account;
use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\AccountProvisioningService;
use App\Services\Shopify\ShopifyService;
use App\Support\ShopifyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $store = $user?->current_account_id
            ? ShopifyStore::query()
                ->forAccount($user->current_account_id)
                ->where('shop_domain', $shop)
                ->first()
            : null;

        if (! $store) {
            $store = ShopifyStore::query()->where('shop_domain', $shop)->first();
        }

        if ($store?->credential?->admin_api_access_token && $user?->belongsToAccount($store->account_id)) {
            $destination = $this->shouldShowOnboarding($store, [
                'created_user' => false,
                'created_account' => false,
            ])
                ? route('onboarding')
                : route('dashboard');

            return redirect()->to($shopifyContext->decorate($destination, $request))
                ->with('status', "{$store->name} is already connected.");
        }

        return redirect()->to($shopifyContext->decorate(route('shopify.install.start', ['shop' => $shop]), $request));
    }

    public function start(Request $request, ShopifyService $shopify): RedirectResponse
    {
        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if ($shop === '' || ! $shopify->isValidShopDomain($shop)) {
            throw ValidationException::withMessages([
                'shop' => 'Enter a valid myshopify.com store domain.',
            ]);
        }

        if ($request->user()) {
            $this->ensureStoreSlotAvailable($request->user()->currentAccount, $shop);
        }

        $state = Str::random(40);

        $request->session()->put('shopify_oauth', [
            'state' => $state,
            'shop' => $shop,
            'account_id' => $request->user()?->current_account_id,
            'user_id' => $request->user()?->id,
            'started_at' => now()->toIso8601String(),
        ]);

        try {
            return redirect()->away($shopify->authorizationUrl($shop, $state));
        } catch (RuntimeException $exception) {
            $target = $request->user() ? route('stores.index') : route('login');

            return redirect()->to($target)->withErrors([
                'shopify' => $exception->getMessage(),
            ]);
        }
    }

    public function callback(
        Request $request,
        ShopifyService $shopify,
        ShopifyContext $shopifyContext,
        AccountProvisioningService $accounts,
    ): RedirectResponse
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

        if ($request->user() && (int) ($oauth['account_id'] ?? 0) !== 0 && (int) ($oauth['account_id'] ?? 0) !== (int) $request->user()->current_account_id) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => 'The active account changed during install. Switch back and retry.',
            ]);
        }

        try {
            $tokenPayload = $shopify->exchangeAuthorizationCode($shop, (string) $request->query('code'));
            $metadata = $shopify->shopDetailsFromAccessToken($shop, $tokenPayload['access_token']);
            $identity = $this->resolveInstallIdentity($request, $accounts, $shop, $metadata);

            Auth::login($identity['user']);
            $request->session()->regenerate();

            $store = $this->upsertInstalledStore($identity['account']->id, $identity['user']->id, $shop, $metadata, $tokenPayload);
        } catch (RuntimeException $exception) {
            return redirect()->to($shopifyContext->decorate(route('stores.index'), $request))->withErrors([
                'shopify' => $exception->getMessage(),
            ]);
        }

        ActivityLog::query()->create([
            'account_id' => $store->account_id,
            'user_id' => $identity['user']->id,
            'subject_type' => $store->getMorphClass(),
            'subject_id' => $store->id,
            'action' => 'shopify_store.oauth_connected',
            'description' => "Shopify OAuth install completed for {$store->name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $status = $identity['created_user']
            ? "Shopify connected for {$store->name}. Your workspace is ready, and you can use {$identity['user']->email} with Forgot Password any time you need a direct sign-in link."
            : "Shopify connected for {$store->name}. Your workspace is ready.";

        $destination = $this->shouldShowOnboarding($store, $identity)
            ? route('onboarding')
            : route('dashboard');

        return redirect()->to($shopifyContext->decorate($destination, $request))
            ->with('status', $status);
    }

    private function upsertInstalledStore(int $accountId, int $userId, string $shop, array $metadata, array $tokenPayload): ShopifyStore
    {
        $store = ShopifyStore::query()->updateOrCreate(
            [
                'account_id' => $accountId,
                'shop_domain' => $shop,
            ],
            [
                'connected_by' => $userId,
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

    private function resolveInstallIdentity(
        Request $request,
        AccountProvisioningService $accounts,
        string $shop,
        array $metadata,
    ): array {
        $existingStore = ShopifyStore::query()
            ->with(['connectedBy', 'account.owner', 'account.users'])
            ->where('shop_domain', $shop)
            ->first();

        if ($existingStore) {
            return $this->resolveExistingStoreIdentity($request, $accounts, $existingStore, $metadata);
        }

        $user = $request->user();
        $createdUser = false;
        $createdAccount = false;

        if (! $user) {
            $user = User::query()->where('email', $this->merchantEmail($metadata))->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $this->merchantName($metadata, $shop),
                    'email' => $this->merchantEmail($metadata),
                    'email_verified_at' => now(),
                    'password' => Str::password(32),
                ]);
                $createdUser = true;
            }
        }

        $account = $user->currentAccount;

        if (! $account) {
            $account = $accounts->createForUser($user, $this->accountName($metadata, $shop));
            $createdAccount = true;
        } else {
            $accounts->ensureMembership($user, $account);
        }

        $this->ensureStoreSlotAvailable($account, $shop);

        return [
            'user' => $user->fresh(),
            'account' => $account->fresh(),
            'created_user' => $createdUser,
            'created_account' => $createdAccount,
        ];
    }

    private function resolveExistingStoreIdentity(
        Request $request,
        AccountProvisioningService $accounts,
        ShopifyStore $existingStore,
        array $metadata,
    ): array {
        $account = $existingStore->account;
        $user = $request->user();

        if ($user && ! $user->belongsToAccount($account) && ! $user->isPlatformAdmin()) {
            throw new RuntimeException('This Shopify store is already connected to another account.');
        }

        if (! $user) {
            $user = $existingStore->connectedBy
                ?: $account?->owner
                ?: $account?->users->first();
        }

        $createdUser = false;

        if (! $user) {
            $user = User::query()->where('email', $this->merchantEmail($metadata))->first();

            if (! $user) {
                $user = User::query()->create([
                    'name' => $this->merchantName($metadata, $existingStore->shop_domain),
                    'email' => $this->merchantEmail($metadata),
                    'email_verified_at' => now(),
                    'password' => Str::password(32),
                ]);
                $createdUser = true;
            }
        }

        $accounts->ensureMembership($user, $account);

        return [
            'user' => $user->fresh(),
            'account' => $account->fresh(),
            'created_user' => $createdUser,
            'created_account' => false,
        ];
    }

    private function ensureStoreSlotAvailable(?Account $account, string $shop): void
    {
        if (! $account) {
            return;
        }

        $existingStore = ShopifyStore::query()
            ->where('account_id', $account->id)
            ->where('shop_domain', $shop)
            ->first();

        if ($existingStore) {
            return;
        }

        $plan = Plan::query()->where('key', $account->plan_key)->first();
        $storeLimit = (int) ($plan?->store_limit ?? 1);
        $storeCount = ShopifyStore::forAccount($account->id)->count();

        if ($storeCount >= $storeLimit) {
            throw ValidationException::withMessages([
                'shop' => "Your {$account?->plan_key} plan allows {$storeLimit} connected store".($storeLimit === 1 ? '' : 's').'. Upgrade before connecting another store.',
            ]);
        }
    }

    private function merchantEmail(array $metadata): string
    {
        $email = (string) ($metadata['contactEmail'] ?? $metadata['email'] ?? '');

        if ($email === '') {
            throw new RuntimeException('Shopify did not return a store contact email, so we could not create your account.');
        }

        return Str::lower($email);
    }

    private function merchantName(array $metadata, string $shop): string
    {
        return (string) ($metadata['name'] ?? Str::headline(Str::before($shop, '.')));
    }

    private function accountName(array $metadata, string $shop): string
    {
        return $this->merchantName($metadata, $shop);
    }

    private function shouldShowOnboarding(ShopifyStore $store, array $identity): bool
    {
        if (($identity['created_user'] ?? false) || ($identity['created_account'] ?? false)) {
            return true;
        }

        return ! $store->products()->exists()
            && ! $store->collections()->exists()
            && ! $store->pages()->exists()
            && ! $store->existingBlogs()->exists();
    }
}
