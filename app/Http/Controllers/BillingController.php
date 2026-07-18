<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ActivityLog;
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
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderByRaw("case `key` when 'free' then 1 when 'starter' then 2 when 'growth' then 3 when 'pro' then 4 else 99 end")
            ->orderBy('id')
            ->get();
        $paidPlans = $plans->filter(fn (Plan $plan) => (float) $plan->monthly_price > 0);
        $misconfiguredPaidPlans = $paidPlans->filter(fn (Plan $plan) => blank($plan->shopify_billing_plan_handle));

        return Inertia::render('Billing/Index', [
            'plans' => $plans,
            'currentPlanKey' => $account?->plan_key ?? 'free',
            'currentSubscription' => $account
                ? $this->currentSubscriptionForAccount($account)
                : null,
            'primaryStore' => $primaryStore,
            'reviewAssets' => [
                'privacy_policy_url' => route('public.privacy'),
                'terms_of_service_url' => route('public.terms'),
                'support_url' => route('public.support'),
                'billing_guide_url' => route('public.billing-guide'),
                'review_guide_url' => route('public.review-guide'),
                'support_email' => config('services.app_review.support_email'),
                'legal_email' => config('services.app_review.legal_email'),
            ],
            'billingReadiness' => [
                'has_connected_store' => (bool) $primaryStore,
                'has_public_app_key' => filled(config('services.shopify.public_app_api_key')),
                'manual_connection_mode' => (bool) config('services.shopify.manual_connection_mode', true),
                'uses_shopify_billing' => true,
                'has_paid_plan_config' => $paidPlans->isNotEmpty(),
                'misconfigured_paid_plans' => $misconfiguredPaidPlans->pluck('name')->values()->all(),
                'has_support_email' => filled(config('services.app_review.support_email')),
                'has_legal_email' => filled(config('services.app_review.legal_email')),
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
            $this->activity(
                $request,
                $account,
                'billing.plan.free_activated',
                'success',
                'Account moved back to the Free plan.',
                $primaryStore,
                $currentSubscription?->only(['id', 'plan_id', 'status', 'external_id']),
                ['plan_key' => 'free']
            );

            return redirect()->route('billing.index')->with('status', 'Moved to the Free plan.');
        }

        if (blank($plan->shopify_billing_plan_handle)) {
            $this->activity(
                $request,
                $account,
                'billing.subscription.subscribe',
                'failed',
                "Subscription start failed because {$plan->name} is missing its Shopify billing handle.",
                null,
                ['plan_id' => $plan->id, 'plan_key' => $plan->key]
            );

            return back()->withErrors([
                'billing' => "The {$plan->name} plan is missing its Shopify billing handle. Configure it before starting subscriptions.",
            ]);
        }

        $primaryStore = $this->primaryStore($request);

        if (! $primaryStore) {
            $this->activity(
                $request,
                $account,
                'billing.subscription.subscribe',
                'failed',
                "Subscription start failed for {$plan->name} because no connected Shopify store was found.",
                null,
                ['plan_id' => $plan->id, 'plan_key' => $plan->key]
            );

            return back()->withErrors([
                'billing' => 'Connect and validate your Shopify store before starting a paid subscription.',
            ]);
        }

        $latestSubscription = $account->subscriptions()
            ->with('plan')
            ->latest('id')
            ->first();

        if (
            $latestSubscription
            && (int) $latestSubscription->plan_id === (int) $plan->id
            && in_array($latestSubscription->status, [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
            ], true)
        ) {
            $this->activity(
                $request,
                $account,
                'billing.subscription.subscribe',
                'success',
                "{$plan->name} is already active for this store.",
                $primaryStore,
                $latestSubscription->only(['id', 'status', 'external_id', 'confirmation_url']),
                ['plan_id' => $plan->id, 'plan_key' => $plan->key]
            );

            return redirect()->route('billing.index')->with('status', "{$plan->name} is already active for this store.");
        }

        if (
            $latestSubscription
            && (int) $latestSubscription->plan_id === (int) $plan->id
            && $latestSubscription->status === Subscription::STATUS_PENDING
            && filled($latestSubscription->confirmation_url)
        ) {
            $this->activity(
                $request,
                $account,
                'billing.subscription.subscribe',
                'success',
                "Reused the pending Shopify approval link for {$plan->name}.",
                $primaryStore,
                $latestSubscription->only(['id', 'status', 'external_id', 'confirmation_url']),
                ['plan_id' => $plan->id, 'plan_key' => $plan->key]
            );

            return redirect()->away($latestSubscription->confirmation_url);
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
            $this->activity(
                $request,
                $account,
                'billing.subscription.subscribe',
                Subscription::STATUS_PENDING,
                "Started Shopify billing approval for {$plan->name}.",
                $primaryStore,
                null,
                [
                    'plan_id' => $plan->id,
                    'plan_key' => $plan->key,
                    'confirmation_url' => $payload['confirmationUrl'] ?? null,
                    'external_id' => $remote['id'] ?? null,
                ]
            );
        } catch (RuntimeException $exception) {
            $this->activity(
                $request,
                $account,
                'billing.subscription.subscribe',
                'failed',
                "Shopify billing approval failed for {$plan->name}: {$exception->getMessage()}",
                $primaryStore,
                null,
                ['plan_id' => $plan->id, 'plan_key' => $plan->key]
            );

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
        $primaryStore = $this->primaryStore($request);

        if (! $account || ! $primaryStore) {
            return redirect()->route('billing.index')->withErrors([
                'billing' => 'We could not find an active Shopify store to confirm billing.',
            ]);
        }

        try {
            $subscription = $billing->syncAccountSubscription($account, $primaryStore);
        } catch (RuntimeException $exception) {
            $this->activity(
                $request,
                $account,
                'billing.subscription.confirm',
                'failed',
                "Billing confirmation failed: {$exception->getMessage()}",
                $primaryStore,
                null,
                ['requested_plan_key' => $request->string('plan')->toString()]
            );

            return redirect()->route('billing.index')->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        $this->activity(
            $request,
            $account,
            'billing.subscription.confirm',
            $subscription?->status ?? 'success',
            $subscription
                ? "Billing confirmed for {$subscription->plan?->name}."
                : 'Billing confirmation completed with no active paid subscription found.',
            $primaryStore,
            null,
            $subscription?->only(['id', 'plan_id', 'status', 'external_id']) ?? ['plan_key' => 'free']
        );

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
            $this->activity(
                $request,
                $account,
                'billing.subscription.sync',
                'failed',
                "Billing sync failed: {$exception->getMessage()}",
                $primaryStore
            );

            return back()->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        $this->activity(
            $request,
            $account,
            'billing.subscription.sync',
            $subscription?->status ?? 'success',
            $subscription
                ? "Billing synced for {$subscription->plan?->name}."
                : 'Billing sync completed and the account remains on Free.',
            $primaryStore,
            null,
            $subscription?->only(['id', 'plan_id', 'status', 'external_id']) ?? ['plan_key' => 'free']
        );

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
            $previous = $subscription->only(['id', 'plan_id', 'status', 'external_id']);
            $billing->cancelSubscription($primaryStore, $subscription->external_id);
            $synced = $billing->syncAccountSubscription($account, $primaryStore);
        } catch (RuntimeException $exception) {
            $this->activity(
                $request,
                $account,
                'billing.subscription.cancel',
                'failed',
                "Billing cancellation failed: {$exception->getMessage()}",
                $primaryStore,
                $subscription->only(['id', 'plan_id', 'status', 'external_id'])
            );

            return back()->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        $this->activity(
            $request,
            $account,
            'billing.subscription.cancel',
            'success',
            'Shopify subscription cancelled and the account moved back to Free.',
            $primaryStore,
            $previous,
            $synced?->only(['id', 'plan_id', 'status', 'external_id']) ?? ['plan_key' => 'free']
        );

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

    private function currentSubscriptionForAccount(Account $account): ?Subscription
    {
        return $account->subscriptions()
            ->with(['plan:id,key,name,monthly_price,shopify_billing_plan_handle', 'store:id,name,shop_domain'])
            ->orderByRaw("case when `status` in ('trialing', 'active', 'pending') then 0 else 1 end")
            ->latest('id')
            ->first();
    }

    private function activity(
        Request $request,
        Account $account,
        string $action,
        string $status,
        string $description,
        ?ShopifyStore $store = null,
        ?array $previous = null,
        ?array $new = null,
    ): void {
        ActivityLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store?->id,
            'user_id' => $request->user()?->id,
            'subject_type' => $store?->getMorphClass() ?? $account->getMorphClass(),
            'subject_id' => $store?->id ?? $account->id,
            'action' => $action,
            'entity_type' => 'billing',
            'status' => $status,
            'description' => $description,
            'previous_values' => $previous,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
