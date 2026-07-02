<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\ShopifyStore;
use App\Models\Subscription;
use App\Services\Shopify\ShopifyBillingService;
use App\Support\ShopifyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeBilling($request);

        $account = $request->user()->currentAccount;
        $primaryStore = $this->primaryStore($request);

        return Inertia::render('Billing/Index', [
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderByRaw("case `key` when 'free' then 1 when 'growth' then 2 when 'pro' then 3 else 99 end")
                ->orderBy('id')
                ->get(),
            'currentPlanKey' => $account?->plan_key ?? 'free',
            'currentSubscription' => $account?->subscriptions()
                ->with(['plan:id,key,name,monthly_price,shopify_billing_plan_handle', 'store:id,name,shop_domain'])
                ->latest('id')
                ->first(),
            'primaryStore' => $primaryStore,
            'billingReadiness' => [
                'has_connected_store' => (bool) $primaryStore,
                'has_public_app_key' => filled(config('services.shopify.public_app_api_key')),
                'manual_connection_mode' => (bool) config('services.shopify.manual_connection_mode', true),
                'uses_shopify_billing' => true,
            ],
        ]);
    }

    public function subscribe(Request $request, Plan $plan, ShopifyBillingService $billing, ShopifyContext $shopifyContext): RedirectResponse
    {
        $this->authorizeBilling($request);

        $account = $request->user()->currentAccount;

        abort_unless($account && $plan->is_active, 404);

        if ((float) $plan->monthly_price <= 0) {
            $currentSubscription = $account->subscriptions()->latest('id')->first();
            $primaryStore = $this->primaryStore($request);

            if ($currentSubscription?->external_id && $primaryStore) {
                $billing->cancelSubscription($primaryStore, $currentSubscription->external_id);
            }

            $billing->activateFreePlan($account);

            return redirect()->route('billing.index')->with('status', 'Moved to the Free plan.');
        }

        $primaryStore = $this->primaryStore($request);

        if (! $primaryStore) {
            return back()->withErrors([
                'billing' => 'Connect and validate your Shopify store before starting a paid subscription.',
            ]);
        }

        try {
            $returnUrl = $shopifyContext->decorate(route('billing.confirm', ['plan' => $plan->key]), $request);
            $payload = $billing->createSubscription($primaryStore, $plan, $returnUrl, max(0, (int) ($plan->trial_days ?? 14)));
            $remote = $payload['appSubscription'] ?? [];

            Subscription::query()->create([
                'account_id' => $account->id,
                'shopify_store_id' => $primaryStore->id,
                'plan_id' => $plan->id,
                'provider' => 'shopify',
                'external_id' => $remote['id'] ?? null,
                'provider_plan_handle' => $plan->shopify_billing_plan_handle ?: $plan->key,
                'provider_line_item_id' => $remote['lineItems'][0]['id'] ?? null,
                'status' => Subscription::STATUS_PENDING,
                'amount' => $plan->monthly_price,
                'currency' => $primaryStore->metadata['currencyCode'] ?? 'USD',
                'is_test' => (bool) ($remote['test'] ?? config('services.shopify.billing_test_mode', false)),
                'confirmation_url' => $payload['confirmationUrl'] ?? null,
                'return_url' => $remote['returnUrl'] ?? $returnUrl,
                'trial_days' => (int) ($remote['trialDays'] ?? 7),
                'metadata' => [
                    'remote' => $remote,
                ],
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        return redirect()->away($payload['confirmationUrl']);
    }

    public function confirm(Request $request, ShopifyBillingService $billing): RedirectResponse
    {
        $this->authorizeBilling($request);

        $account = $request->user()->currentAccount;
        $plan = Plan::query()->where('key', $request->string('plan'))->first();
        $primaryStore = $this->primaryStore($request);

        if (! $account || ! $primaryStore) {
            return redirect()->route('billing.index')->withErrors([
                'billing' => 'We could not find an active Shopify store to confirm billing.',
            ]);
        }

        try {
            $subscription = $billing->syncAccountSubscription($account, $primaryStore, $plan);
        } catch (RuntimeException $exception) {
            return redirect()->route('billing.index')->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('billing.index')->with('status', $subscription
            ? "Billing confirmed. {$subscription->plan?->name} is now active."
            : 'Billing check completed. No active paid subscription was found.');
    }

    public function sync(Request $request, ShopifyBillingService $billing): RedirectResponse
    {
        $this->authorizeBilling($request);

        $account = $request->user()->currentAccount;
        $primaryStore = $this->primaryStore($request);

        if (! $account || ! $primaryStore) {
            return back()->withErrors([
                'billing' => 'Connect your Shopify store before syncing billing.',
            ]);
        }

        try {
            $subscription = $billing->syncAccountSubscription($account, $primaryStore);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', $subscription
            ? "Billing synced. {$subscription->plan?->name} is active."
            : 'Billing synced. No active paid subscription was found, so the account is on Free.');
    }

    public function cancel(Request $request, ShopifyBillingService $billing): RedirectResponse
    {
        $this->authorizeBilling($request);

        $account = $request->user()->currentAccount;
        $primaryStore = $this->primaryStore($request);
        $subscription = $account?->subscriptions()->latest('id')->first();

        if (! $account || ! $subscription?->external_id || ! $primaryStore) {
            return back()->withErrors([
                'billing' => 'There is no active Shopify subscription to cancel.',
            ]);
        }

        try {
            $billing->cancelSubscription($primaryStore, $subscription->external_id);
            $billing->syncAccountSubscription($account, $primaryStore);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', 'Shopify subscription cancelled and account moved back to Free.');
    }

    private function authorizeBilling(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->isPlatformAdmin()
            || $user?->hasAccountPermission('billing.manage')
            || $user?->hasAccountPermission('stores.manage'),
            403
        );
    }

    private function primaryStore(Request $request): ?ShopifyStore
    {
        return ShopifyStore::query()
            ->forAccount($request->user()->current_account_id)
            ->where('status', 'connected')
            ->with('credential')
            ->latest('id')
            ->first();
    }
}
