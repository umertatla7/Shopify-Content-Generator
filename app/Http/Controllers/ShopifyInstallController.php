<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Notifications\NewShopifySignupNotification;
use App\Services\AccountProvisioningService;
use App\Services\Shopify\ShopifyService;
use App\Support\ShopifyContext;
use App\Support\ShopifySessionToken;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ShopifyInstallController extends Controller
{
    private const OAUTH_CACHE_PREFIX = 'shopify_oauth:';

    private const SESSION_HANDOFF_CACHE_PREFIX = 'shopify_session_handoff:';

    public function app(Request $request, ShopifyService $shopify, ShopifyContext $shopifyContext): RedirectResponse|Response|View
    {
        $shopifyContext->remember($request);

        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if ($shop === '' || ! $shopify->isValidShopDomain($shop)) {
            if ($this->isEmbeddedRequest($request)) {
                return response(
                    'A valid Shopify shop context is required. Reopen GrowShopHigh from Shopify admin.',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                )->withHeaders([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }

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

        if ($store?->status === 'reconnect_required') {
            return redirect()->to($shopifyContext->decorate(route('shopify.install.start', ['shop' => $shop]), $request));
        }

        if ($this->isEmbeddedRequest($request) && $store?->credential?->admin_api_access_token) {
            return response()->view('shopify-session', [
                'apiKey' => $shopify->publicAppApiKey(),
                'shop' => $shop,
                'host' => (string) $request->query('host', ''),
                'handoff' => $this->createSessionHandoff($request, $shop),
                'target' => route('shopify.session'),
            ], Response::HTTP_OK)->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        if (! $request->user() && $store?->credential?->admin_api_access_token) {

            return redirect()->to($shopifyContext->decorate(route('shopify.install.start', ['shop' => $shop]), $request));
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

    public function session(
        Request $request,
        ShopifyService $shopify,
        ShopifyContext $shopifyContext,
        ShopifySessionToken $sessionToken,
    ): RedirectResponse {
        $shopifyContext->remember($request);

        $shop = $shopify->normalizeDomain((string) $request->input('shop', ''));

        if ($shop === '' || ! $shopify->isValidShopDomain($shop)) {
            throw ValidationException::withMessages([
                'shopify' => 'A valid Shopify shop domain is required.',
            ]);
        }

        try {
            $verifiedSession = $sessionToken->fromRequest($request, $shop);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'shopify' => $exception->getMessage(),
            ]);
        }

        $jti = (string) ($verifiedSession['payload']['jti'] ?? '');
        $expiresAt = (int) ($verifiedSession['payload']['exp'] ?? 0);
        $handoff = (string) $request->input('handoff', '');
        $handoffContext = $handoff !== ''
            ? Cache::pull($this->sessionHandoffCacheKey($handoff))
            : null;

        if (! is_array($handoffContext) || ! $this->handoffMatchesRequest($handoffContext, $request, $shop)) {
            throw ValidationException::withMessages([
                'shopify' => 'This Shopify session handoff expired or did not match this browser. Reopen the app from Shopify admin.',
            ]);
        }

        $handoffKey = self::SESSION_HANDOFF_CACHE_PREFIX.hash('sha256', $shop.'|'.$jti);

        if ($jti === '' || ! Cache::add($handoffKey, true, max(1, $expiresAt - time()))) {
            throw ValidationException::withMessages([
                'shopify' => 'This Shopify session handoff was already used. Reopen the app from Shopify admin.',
            ]);
        }

        $store = ShopifyStore::query()
            ->with(['connectedBy', 'account.owner', 'account.users', 'credential'])
            ->where('shop_domain', $shop)
            ->first();

        if (! $store?->credential?->admin_api_access_token) {
            return redirect()->to($shopifyContext->decorate(route('shopify.install.start', ['shop' => $shop]), $request));
        }

        $account = $store->account;
        $user = $store->connectedBy ?: $account?->owner ?: $account?->users->first();

        if (! $account || ! $user) {
            throw ValidationException::withMessages([
                'shopify' => 'This Shopify install is missing its workspace owner. Contact support.',
            ]);
        }

        $user->forceFill([
            'current_account_id' => $account->id,
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('shopify_verified_context', [
            'shop' => $shop,
            'account_id' => $account->id,
            'sid' => (string) ($verifiedSession['payload']['sid'] ?? ''),
            'host_hash' => hash('sha256', (string) $request->input('host', '')),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            'expires_at' => $expiresAt,
        ]);

        $destination = $this->shouldShowOnboarding($store, [
            'created_user' => false,
            'created_account' => false,
        ])
            ? route('onboarding')
            : route('dashboard');

        return redirect()->to($shopifyContext->decorate($destination, $request))
            ->with('status', "{$store->name} is ready.");
    }

    public function start(Request $request, ShopifyService $shopify): RedirectResponse|View
    {
        $shop = $shopify->normalizeDomain((string) $request->query('shop', ''));

        if ($shop === '' || ! $shopify->isValidShopDomain($shop)) {
            throw ValidationException::withMessages([
                'shop' => 'Enter a valid myshopify.com store domain.',
            ]);
        }

        if ($this->manualConnectionEnabled() && $request->user() && ! $this->isEmbeddedRequest($request)) {
            $this->ensureStoreSlotAvailable($request->user()->currentAccount, $shop);
        }

        $state = Str::random(40);

        $oauth = [
            'state' => $state,
            'shop' => $shop,
            'account_id' => $this->manualConnectionEnabled() ? $request->user()?->current_account_id : null,
            'user_id' => $this->manualConnectionEnabled() ? $request->user()?->id : null,
            'started_at' => now()->toIso8601String(),
        ];
        $request->session()->put('shopify_oauth', $oauth);
        Cache::put($this->oauthCacheKey($state), $oauth, now()->addMinutes(15));

        try {
            $target = $shopify->authorizationUrl($shop, $state);

            if ($this->isEmbeddedRequest($request)) {
                return view('shopify-redirect', [
                    'target' => $target,
                    'message' => 'Redirecting to Shopify to finish authorization...',
                ]);
            }

            return redirect()->away($target);
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
    ): RedirectResponse|Response|View {
        $state = (string) $request->query('state', '');
        $sessionOauth = $request->session()->pull('shopify_oauth');
        $cachedOauth = $state !== '' ? Cache::pull($this->oauthCacheKey($state)) : null;
        $oauth = $sessionOauth;

        if (
            (! $oauth || (string) ($oauth['state'] ?? '') !== $state)
            && is_array($cachedOauth)
        ) {
            $oauth = $cachedOauth;
        }

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

        if ($state !== (string) ($oauth['state'] ?? '')) {
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

        $this->notifyAdminOfNewSignup($identity, $store);

        $status = $identity['created_user']
            ? "Shopify connected for {$store->name}. Your workspace is ready, and you can use {$identity['user']->email} with Forgot Password any time you need a direct sign-in link."
            : "Shopify connected for {$store->name}. Your workspace is ready.";

        if ($this->isEmbeddedRequest($request)) {
            $redirectTo = $shopifyContext->embeddedAppUrl($request, '/shopify/app', [
                'embedded' => '1',
                'shop' => $shop,
            ]);

            if ($redirectTo) {
                return response()->view('shopify-redirect', [
                    'target' => $redirectTo,
                    'message' => 'Redirecting back to Shopify to finish opening your app...',
                ], Response::HTTP_OK)->withHeaders([
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }
        }

        $destination = $this->shouldShowOnboarding($store, $identity)
            ? route('onboarding')
            : route('dashboard');
        $redirectTo = $shopifyContext->decorate($destination, $request);

        return redirect()->to($redirectTo)
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
                'refresh_token' => $tokenPayload['refresh_token'],
                'api_key' => config('services.shopify.public_app_api_key'),
                'client_secret' => config('services.shopify.public_app_client_secret'),
                'scopes' => $scopes ?: null,
                'expires_at' => now()->addSeconds((int) $tokenPayload['expires_in']),
                'refresh_token_expires_at' => isset($tokenPayload['refresh_token_expires_in'])
                    ? now()->addSeconds((int) $tokenPayload['refresh_token_expires_in'])
                    : null,
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

        $user = $this->manualConnectionEnabled() ? $request->user() : null;
        $createdUser = false;
        $createdAccount = false;

        if (! $user) {
            $user = User::query()->create([
                'name' => $this->merchantName($metadata, $shop),
                'email' => $this->uniqueInstallEmail($this->merchantEmail($metadata), $shop),
                'email_verified_at' => now(),
                'password' => Str::password(32),
            ]);
            $createdUser = true;
        }

        $account = $user->currentAccount;

        if (! $user && $account && ! $this->accountCanAcceptStore($account, $shop)) {
            $account = null;
        }

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
        $user = $existingStore->connectedBy
            ?: $account?->owner
            ?: $account?->users->first();

        $createdUser = false;

        if (! $user) {
            $user = User::query()->create([
                'name' => $this->merchantName($metadata, $existingStore->shop_domain),
                'email' => $this->uniqueInstallEmail($this->merchantEmail($metadata), $existingStore->shop_domain),
                'email_verified_at' => now(),
                'password' => Str::password(32),
            ]);
            $createdUser = true;
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

    private function accountCanAcceptStore(Account $account, string $shop): bool
    {
        $existingStore = ShopifyStore::query()
            ->where('account_id', $account->id)
            ->where('shop_domain', $shop)
            ->exists();

        if ($existingStore) {
            return true;
        }

        $plan = Plan::query()->where('key', $account->plan_key)->first();
        $storeLimit = (int) ($plan?->store_limit ?? 1);
        $storeCount = ShopifyStore::forAccount($account->id)->count();

        return $storeCount < $storeLimit;
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

    private function oauthCacheKey(string $state): string
    {
        return self::OAUTH_CACHE_PREFIX.$state;
    }

    private function createSessionHandoff(Request $request, string $shop): string
    {
        $handoff = Str::random(64);

        Cache::put($this->sessionHandoffCacheKey($handoff), [
            'shop' => $shop,
            'host_hash' => hash('sha256', (string) $request->query('host', '')),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
        ], now()->addMinutes(2));

        return $handoff;
    }

    private function sessionHandoffCacheKey(string $handoff): string
    {
        return self::SESSION_HANDOFF_CACHE_PREFIX.hash('sha256', $handoff);
    }

    private function handoffMatchesRequest(array $handoff, Request $request, string $shop): bool
    {
        return hash_equals((string) ($handoff['shop'] ?? ''), $shop)
            && hash_equals(
                (string) ($handoff['host_hash'] ?? ''),
                hash('sha256', (string) $request->input('host', '')),
            )
            && hash_equals(
                (string) ($handoff['user_agent_hash'] ?? ''),
                hash('sha256', (string) $request->userAgent()),
            );
    }

    private function isEmbeddedRequest(Request $request): bool
    {
        if ($request->boolean('embedded')) {
            return true;
        }

        return filled((string) $request->query('host', ''));
    }

    private function manualConnectionEnabled(): bool
    {
        return (bool) config('services.shopify.manual_connection_mode', false);
    }

    private function uniqueInstallEmail(string $email, string $shop): string
    {
        $email = Str::lower($email);

        if (! User::query()->where('email', $email)->exists()) {
            return $email;
        }

        $local = Str::before($email, '@') ?: 'shopify';
        $domain = Str::after($email, '@') ?: 'example.com';
        $shopSlug = Str::slug(Str::before($shop, '.')) ?: 'shop';
        $candidate = "{$local}+{$shopSlug}@{$domain}";
        $index = 2;

        while (User::query()->where('email', $candidate)->exists()) {
            $candidate = "{$local}+{$shopSlug}-{$index}@{$domain}";
            $index++;
        }

        return $candidate;
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

    private function notifyAdminOfNewSignup(array $identity, ShopifyStore $store): void
    {
        if (! ($identity['created_user'] ?? false) && ! ($identity['created_account'] ?? false)) {
            return;
        }

        $recipient = config('services.app_review.support_email');

        if (! filled($recipient)) {
            return;
        }

        $account = $identity['account']?->fresh('plan');
        $user = $identity['user']?->fresh();

        if (! $account || ! $user) {
            return;
        }

        $planName = $account->plan?->name
            ?? Plan::query()->where('key', $account->plan_key)->value('name')
            ?? Str::headline((string) $account->plan_key);

        Notification::route('mail', $recipient)->notify(
            new NewShopifySignupNotification($user, $account, $store, $planName)
        );
    }
}
