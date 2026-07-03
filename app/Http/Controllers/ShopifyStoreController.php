<?php

namespace App\Http\Controllers;

use App\Jobs\SyncShopifyStoreJob;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use App\Services\Shopify\ShopifyService;
use App\Services\Shopify\ShopifySyncService;
use App\Support\PlanFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ShopifyStoreController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.stores.index');
        }

        $this->authorize('viewAny', ShopifyStore::class);

        $accountId = $request->user()->current_account_id;
        $account = $request->user()->currentAccount;

        return Inertia::render('Stores/Index', $this->storesPayload($accountId, $account, 'manage'));
    }

    public function audit(Request $request): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.stores.index');
        }

        $this->authorize('viewAny', ShopifyStore::class);

        $accountId = $request->user()->current_account_id;
        $account = $request->user()->currentAccount;

        if (! PlanFeatureGate::moduleAccess($account)['store_audit']) {
            return Inertia::render('FeaturePreview', PlanFeatureGate::preview('store_audit'));
        }

        return Inertia::render('Stores/Index', $this->storesPayload($accountId, $account, 'audit'));
    }

    public function store(Request $request, ShopifyService $shopify): RedirectResponse
    {
        $this->authorize('create', ShopifyStore::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'shop_url' => ['required', 'string', 'max:255'],
            'admin_api_access_token' => ['nullable', 'string', 'max:4000'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:64'],
            'default_language' => ['required', 'string', 'max:16'],
            'brand_tone' => ['nullable', 'string', 'max:255'],
        ]);

        if (
            blank($validated['admin_api_access_token'] ?? null)
            && (blank($validated['api_key'] ?? null) || blank($validated['client_secret'] ?? null))
        ) {
            throw ValidationException::withMessages([
                'api_key' => 'Enter the Shopify Client ID and Client Secret, or paste an Admin API access token.',
                'client_secret' => 'Enter the Shopify Client ID and Client Secret, or paste an Admin API access token.',
            ]);
        }

        $domain = $shopify->normalizeDomain($validated['shop_url']);
        $existingStore = ShopifyStore::query()
            ->where('account_id', $request->user()->current_account_id)
            ->where('shop_domain', $domain)
            ->first();

        if (! $existingStore) {
            $account = $request->user()->currentAccount;
            $plan = $account ? Plan::query()->where('key', $account->plan_key)->first() : null;
            $storeLimit = (int) ($plan?->store_limit ?? 1);
            $storeCount = ShopifyStore::forAccount($request->user()->current_account_id)->count();

            if ($storeCount >= $storeLimit) {
                throw ValidationException::withMessages([
                    'shop_url' => "Your {$account?->plan_key} plan allows {$storeLimit} connected store".($storeLimit === 1 ? '' : 's').'. Ask admin to upgrade your package to connect another store.',
                ]);
            }
        }

        $store = ShopifyStore::query()->updateOrCreate(
            [
                'account_id' => $request->user()->current_account_id,
                'shop_domain' => $domain,
            ],
            [
                'connected_by' => $request->user()->id,
                'name' => $validated['name'],
                'shop_url' => 'https://'.$domain,
                'country' => $validated['country'] ?? null,
                'default_language' => $validated['default_language'],
                'brand_tone' => $validated['brand_tone'] ?? null,
                'status' => 'pending',
            ]
        );

        $store->credential()->updateOrCreate(
            ['shopify_store_id' => $store->id],
            [
                'account_id' => $store->account_id,
                'admin_api_access_token' => filled($validated['admin_api_access_token'] ?? null) ? $validated['admin_api_access_token'] : null,
                'api_key' => $validated['api_key'] ?? null,
                'client_secret' => $validated['client_secret'] ?? null,
                'expires_at' => null,
            ]
        );

        try {
            $metadata = $shopify->validateConnection($store->fresh('credential'));
            $store->update([
                'status' => 'connected',
                'metadata' => $metadata,
                'country' => $metadata['shopAddress']['countryCode'] ?? $store->country,
                'currency' => $metadata['currencyCode'] ?? $store->currency,
                'timezone' => $metadata['ianaTimezone'] ?? $store->timezone,
                'primary_locale' => $store->default_language,
                'last_validated_at' => now(),
                'validation_error' => null,
            ]);
        } catch (Throwable $exception) {
            $store->update([
                'status' => 'disconnected',
                'validation_error' => $exception->getMessage(),
            ]);
        }

        ActivityLog::query()->create([
            'account_id' => $store->account_id,
            'user_id' => $request->user()->id,
            'subject_type' => $store->getMorphClass(),
            'subject_id' => $store->id,
            'action' => 'shopify_store.connected',
            'description' => "Shopify store {$store->name} credentials were saved.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'Store credentials saved.');
    }

    public function sync(Request $request, ShopifyStore $store, ShopifySyncService $sync): RedirectResponse
    {
        $this->authorize('sync', $store);

        $log = ShopifySyncLog::query()->create([
            'account_id' => $store->account_id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'full',
            'status' => 'pending',
        ]);

        if (! config('services.shopify.sync_via_queue', false) || app()->environment('local') || config('queue.default') === 'sync') {
            $this->extendExecutionLimit();
            $log = $sync->syncStore($store->loadMissing('credential'), $log);

            return back()->with('status', $log->status === 'completed'
                ? 'Shopify sync completed.'
                : 'Shopify sync failed: '.$log->error_message);
        }

        SyncShopifyStoreJob::dispatch($store->id, $log->id);

        return back()->with('status', 'Shopify sync started.');
    }

    public function destroy(ShopifyStore $store): RedirectResponse
    {
        $this->authorize('delete', $store);

        $store->delete();

        return back()->with('status', 'Store removed.');
    }

    private function storesPayload(int|string|null $accountId, mixed $account, string $mode): array
    {
        $plan = $account ? Plan::query()->where('key', $account->plan_key)->first() : null;
        $storeLimit = (int) ($plan?->store_limit ?? 1);
        $storeCount = ShopifyStore::forAccount($accountId)->count();

        return [
            'mode' => $mode,
            'storeLimit' => $storeLimit,
            'storeCount' => $storeCount,
            'canAddStore' => $storeCount < $storeLimit,
            'stores' => ShopifyStore::query()
                ->forAccount($accountId)
                ->with(['latestSyncLog', 'knowledgeBase', 'latestAnalysis'])
                ->withCount(['products', 'collections', 'pages', 'blogs'])
                ->latest()
                ->get(),
        ];
    }

    private function extendExecutionLimit(int $seconds = 120): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }
}
