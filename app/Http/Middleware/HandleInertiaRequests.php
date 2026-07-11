<?php

namespace App\Http\Middleware;

use App\Models\ShopifyStore;
use App\Support\PlanFeatureGate;
use App\Support\ShopifyContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $account = $user?->currentAccount;
        $primaryStore = $account?->id
            ? ShopifyStore::query()
                ->forAccount($account->id)
                ->where('status', 'connected')
                ->withCount(['products', 'collections', 'pages', 'existingBlogs'])
                ->latest('id')
                ->first()
            : null;
        $catalogSynced = $primaryStore
            ? (($primaryStore->products_count + $primaryStore->collections_count + $primaryStore->pages_count + $primaryStore->existing_blogs_count) > 0)
            : false;
        $shopifyContext = app(ShopifyContext::class)->props($request);
        $planAccess = PlanFeatureGate::moduleAccess($account);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'global_role' => $user->global_role,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'is_platform_admin' => $user->isPlatformAdmin(),
                ] : null,
                'account' => $account ? [
                    'id' => $account->id,
                    'name' => $account->name,
                    'slug' => $account->slug,
                    'plan_key' => $account->plan_key,
                    'plan_features' => PlanFeatureGate::features($account),
                    'plan_access' => $planAccess,
                ] : null,
                'permissions' => $user ? [
                    'stores.view' => $user->hasAccountPermission('stores.view'),
                    'stores.manage' => $user->hasAccountPermission('stores.manage'),
                    'stores.sync' => $user->hasAccountPermission('stores.sync'),
                    'analysis.run' => $user->hasAccountPermission('analysis.run'),
                    'topics.manage' => $user->hasAccountPermission('topics.manage'),
                    'blogs.edit' => $user->hasAccountPermission('blogs.edit'),
                    'blogs.approve' => $user->hasAccountPermission('blogs.approve'),
                    'blogs.publish' => $user->hasAccountPermission('blogs.publish'),
                    'billing.manage' => $user->hasAccountPermission('billing.manage'),
                    'team.manage' => $user->hasAccountPermission('team.manage'),
                ] : [],
                'plan_access' => $planAccess,
                'setup' => [
                    'store_connected' => (bool) $primaryStore,
                    'catalog_synced' => $catalogSynced,
                    'requires_catalog_sync' => (bool) $primaryStore && ! $catalogSynced,
                    'primary_store_id' => $primaryStore?->id,
                ],
            ],
            'shopify' => $shopifyContext,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
