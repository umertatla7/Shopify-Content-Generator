<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\SupportTicket;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\AICostService;
use App\Services\Shopify\ShopifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AdminAccountController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $filters = $request->only(['search']);

        return Inertia::render('Admin/Accounts/Index', [
            'filters' => $filters,
            'accounts' => Account::query()
                ->with([
                    'owner:id,name,email',
                    'stores:id,account_id,name,shop_domain,shop_url,status,last_synced_at',
                ])
                ->withCount(['users', 'stores', 'blogs', 'topics', 'aiGenerations'])
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('billing_email', 'like', "%{$search}%");
                }))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        return Inertia::render('Admin/Accounts/Create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ShopifyService $shopify): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'send_invite' => ['boolean'],
            'company_name' => ['required', 'string', 'max:255'],
            'plan_key' => ['required', 'string', 'exists:plans,key'],
            'credit_balance' => ['required', 'integer', 'min:0', 'max:10000000'],
            'store_url' => ['nullable', 'string', 'max:255'],
            'shopify_access_token' => ['nullable', 'string', 'max:4000'],
            'shopify_api_key' => ['nullable', 'string', 'max:2000'],
            'shopify_client_secret' => ['nullable', 'string', 'max:2000'],
            'store_region' => ['nullable', 'string', 'max:64'],
            'store_language' => ['nullable', 'string', 'max:16'],
            'brand_tone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $plan = Plan::query()->where('key', $validated['plan_key'])->firstOrFail();
        $role = Role::query()->where('name', 'customer_admin')->first();
        $membershipStatus = ($validated['status'] === 'active' && ! $request->boolean('send_invite')) ? 'active' : 'invited';

        $account = DB::transaction(function () use ($request, $validated, $plan, $role, $membershipStatus, $shopify): Account {
            $user = User::query()->create([
                'name' => $validated['customer_name'],
                'email' => $validated['email'],
                'email_verified_at' => now(),
                'password' => $validated['password'] ?: Str::password(16),
                'global_role' => 'user',
            ]);

            $account = Account::query()->create([
                'owner_id' => $user->id,
                'name' => $validated['company_name'],
                'slug' => $this->uniqueAccountSlug($validated['company_name']),
                'billing_email' => $validated['email'],
                'region' => $validated['store_region'] ?? null,
                'timezone' => config('app.timezone'),
                'plan_key' => $plan->key,
                'status' => $validated['status'],
                'credit_balance' => (int) $validated['credit_balance'],
                'monthly_credit_allowance' => (int) ($plan->monthly_credit_allowance ?? $validated['credit_balance']),
                'credits_expire_at' => $plan->credit_expires_after_days ? now()->addDays((int) $plan->credit_expires_after_days) : null,
            ]);

            $account->users()->attach($user->id, [
                'role_id' => $role?->id,
                'status' => $membershipStatus,
                'invited_by' => $request->user()->id,
                'invited_at' => $request->boolean('send_invite') ? now() : null,
                'accepted_at' => $membershipStatus === 'active' ? now() : null,
            ]);

            $user->forceFill(['current_account_id' => $account->id])->save();

            if (filled($validated['store_url'] ?? null)) {
                $this->createAdminManagedStore($account, $user, $validated, $shopify);
            }

            UsageLog::query()->create([
                'account_id' => $account->id,
                'user_id' => $request->user()->id,
                'type' => 'credit_admin_adjustment',
                'quantity' => (int) $validated['credit_balance'],
                'unit' => 'credit',
                'metadata' => [
                    'action' => 'initial_credit_balance',
                    'plan_key' => $plan->key,
                ],
            ]);

            $this->activity($request, $account, 'admin.customer.created', 'customer', 'success', "Admin created customer {$account->name}.", null, [
                'account_name' => $account->name,
                'owner_email' => $user->email,
                'plan_key' => $account->plan_key,
                'credit_balance' => $account->credit_balance,
                'status' => $account->status,
            ]);

            return $account;
        });

        return redirect()->route('admin.accounts.show', $account)->with('status', 'Customer account created.');
    }

    public function show(Request $request, Account $account, AICostService $costs): Response
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $account->load([
            'owner:id,name,email',
            'users:id,name,email,global_role',
        ])->loadCount(['users', 'stores', 'blogs', 'topics', 'aiGenerations']);

        $stores = $account->stores()
            ->with(['credential:id,shopify_store_id,expires_at,scopes,created_at,updated_at'])
            ->withCount(['products', 'collections', 'blogs', 'pages', 'existingBlogs', 'visibilityReports'])
            ->latest()
            ->get();

        $generations = $account->aiGenerations()
            ->with(['user:id,name,email'])
            ->latest()
            ->get(['id', 'account_id', 'shopify_store_id', 'user_id', 'provider', 'model', 'type', 'status', 'token_usage', 'cost', 'created_at']);

        $monthlyGenerations = $generations->filter(fn ($generation) => $generation->created_at?->gte(now()->startOfMonth()));
        $creditUsageQuery = $account->usageLogs()->where('type', 'credit_usage');

        return Inertia::render('Admin/Accounts/Show', [
            'account' => $account,
            'plans' => Plan::query()->where('is_active', true)->orderBy('name')->get(),
            'stores' => $stores,
            'blogs' => $account->blogs()->with('store:id,name')->latest()->limit(20)->get(),
            'activity' => $account->activityLogs()->with(['user:id,name,email', 'store:id,name'])->latest()->limit(20)->get(),
            'supportTickets' => SupportTicket::query()
                ->with(['store:id,name,shop_domain', 'openedBy:id,name,email'])
                ->withCount('messages')
                ->where('account_id', $account->id)
                ->latest('last_message_at')
                ->limit(20)
                ->get(),
            'creditUsage' => $account->usageLogs()
                ->where('type', 'credit_usage')
                ->latest()
                ->limit(20)
                ->get(['id', 'type', 'quantity', 'unit', 'metadata', 'created_at']),
            'aiCostSummary' => [
                'all_time' => $this->summarizeGenerations($generations, $costs),
                'current_month' => $this->summarizeGenerations($monthlyGenerations, $costs),
            ],
            'creditsUsedSummary' => [
                'all_time' => (int) (clone $creditUsageQuery)->sum('quantity'),
                'current_month' => (int) (clone $creditUsageQuery)->where('created_at', '>=', now()->startOfMonth())->sum('quantity'),
            ],
            'recentFailures' => [
                'sync' => $stores->map(function (ShopifyStore $store): ?array {
                    $failedSync = $store->syncLogs()->where('status', 'failed')->latest()->first(['id', 'status', 'error_message', 'created_at']);

                    if (! $failedSync) {
                        return null;
                    }

                    return [
                        'id' => $failedSync->id,
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'shop_domain' => $store->shop_domain,
                        'status' => $failedSync->status,
                        'error_message' => $failedSync->error_message,
                        'created_at' => $failedSync->created_at,
                    ];
                })->filter()->values(),
                'analysis' => $stores->flatMap(function (ShopifyStore $store) {
                    return $store->analyses()
                        ->where('status', 'failed')
                        ->latest()
                        ->limit(3)
                        ->get(['id', 'shopify_store_id', 'status', 'error_message', 'created_at'])
                        ->map(fn ($analysis) => [
                            'id' => $analysis->id,
                            'store_id' => $store->id,
                            'store_name' => $store->name,
                            'shop_domain' => $store->shop_domain,
                            'status' => $analysis->status,
                            'error_message' => $analysis->error_message,
                            'created_at' => $analysis->created_at,
                        ]);
                })->take(10)->values(),
            ],
        ]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'region' => ['nullable', 'string', 'max:64'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'in:active,inactive'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->owner_id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $previous = $account->only(['name', 'billing_email', 'region', 'timezone', 'status']);

        $account->update([
            'name' => $validated['name'],
            'billing_email' => $validated['billing_email'] ?? null,
            'region' => $validated['region'] ?? null,
            'timezone' => $validated['timezone'] ?: $account->timezone,
            'status' => $validated['status'],
        ]);

        if ($account->owner) {
            $ownerUpdates = array_filter([
                'name' => $validated['owner_name'] ?? null,
                'email' => $validated['owner_email'] ?? null,
                'password' => $validated['password'] ?? null,
            ], fn ($value) => filled($value));

            if ($ownerUpdates) {
                $account->owner->update($ownerUpdates);
            }
        }

        $this->activity($request, $account, 'admin.customer.updated', 'customer', 'success', "Admin updated customer {$account->name}.", $previous, $account->only(['name', 'billing_email', 'region', 'timezone', 'status']));

        return back()->with('status', 'Customer updated.');
    }

    public function updatePackage(Request $request, Account $account): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'plan_key' => ['required', 'string', 'max:64', 'exists:plans,key'],
            'monthly_credit_allowance' => ['required', 'integer', 'min:0', 'max:10000000'],
            'credit_balance' => ['required', 'integer', 'min:0', 'max:10000000'],
            'credits_expire_at' => ['nullable', 'date'],
        ]);

        $previous = $account->only(['plan_key', 'monthly_credit_allowance', 'credit_balance', 'credits_expire_at']);
        $account->update($validated);

        UsageLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $request->user()->id,
            'type' => 'credit_admin_adjustment',
            'quantity' => (int) $validated['credit_balance'],
            'unit' => 'credit',
            'metadata' => [
                'plan_key' => $validated['plan_key'],
                'monthly_credit_allowance' => (int) $validated['monthly_credit_allowance'],
                'credit_balance' => (int) $validated['credit_balance'],
                'credits_expire_at' => $validated['credits_expire_at'] ?? null,
            ],
        ]);

        $this->activity($request, $account, 'admin.package.updated', 'plan', 'success', "Admin changed package for {$account->name}.", $previous, $account->only(['plan_key', 'monthly_credit_allowance', 'credit_balance', 'credits_expire_at']));

        return back()->with('status', 'Account package updated.');
    }

    public function adjustCredits(Request $request, Account $account): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        $validated = $request->validate([
            'credits' => ['required', 'integer', 'min:-10000000', 'max:10000000', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
            'credits_expire_at' => ['nullable', 'date'],
        ]);

        $previous = $account->only(['credit_balance', 'credits_expire_at']);
        $newBalance = max(0, (int) $account->credit_balance + (int) $validated['credits']);

        $account->update([
            'credit_balance' => $newBalance,
            'credits_expire_at' => $validated['credits_expire_at'] ?? $account->credits_expire_at,
        ]);

        UsageLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $request->user()->id,
            'type' => 'credit_admin_adjustment',
            'quantity' => abs((int) $validated['credits']),
            'unit' => 'credit',
            'metadata' => [
                'action' => (int) $validated['credits'] > 0 ? 'bonus_credits_added' : 'credits_removed',
                'delta' => (int) $validated['credits'],
                'note' => $validated['note'] ?? null,
            ],
        ]);

        $this->activity($request, $account, 'admin.credits.adjusted', 'credit', 'success', "Admin adjusted credits by {$validated['credits']} for {$account->name}.", $previous, $account->only(['credit_balance', 'credits_expire_at']));

        return back()->with('status', 'Credits adjusted.');
    }

    public function updateStore(Request $request, Account $account, ShopifyStore $store, ShopifyService $shopify): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);
        abort_unless($store->account_id === $account->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'shop_url' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:64'],
            'default_language' => ['required', 'string', 'max:16'],
            'brand_tone' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,connected,disconnected,inactive'],
            'admin_api_access_token' => ['nullable', 'string', 'max:4000'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'reset_credentials' => ['boolean'],
        ]);

        $previous = $store->only(['name', 'shop_domain', 'country', 'default_language', 'brand_tone', 'status']);
        $domain = $shopify->normalizeDomain($validated['shop_url']);

        $store->update([
            'name' => $validated['name'],
            'shop_domain' => $domain,
            'shop_url' => 'https://'.$domain,
            'country' => $validated['country'] ?? null,
            'default_language' => $validated['default_language'],
            'brand_tone' => $validated['brand_tone'] ?? null,
            'status' => $validated['status'],
        ]);

        $credentialPayload = [];

        if ($request->boolean('reset_credentials')) {
            $credentialPayload = [
                'admin_api_access_token' => filled($validated['admin_api_access_token'] ?? null) ? $validated['admin_api_access_token'] : null,
                'api_key' => filled($validated['api_key'] ?? null) ? $validated['api_key'] : null,
                'client_secret' => filled($validated['client_secret'] ?? null) ? $validated['client_secret'] : null,
                'expires_at' => null,
                'scopes' => null,
            ];
        } else {
            foreach (['admin_api_access_token', 'api_key', 'client_secret'] as $field) {
                if (filled($validated[$field] ?? null)) {
                    $credentialPayload[$field] = $validated[$field];
                }
            }
        }

        if ($credentialPayload) {
            $store->credential()->updateOrCreate(
                ['shopify_store_id' => $store->id],
                ['account_id' => $store->account_id, ...$credentialPayload]
            );
        }

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
                'status' => $validated['status'] === 'inactive' ? 'inactive' : 'disconnected',
                'validation_error' => $exception->getMessage(),
            ]);
        }

        $this->activity($request, $account, 'admin.shopify_credentials.updated', 'store', $store->status === 'connected' ? 'success' : 'failed', "Admin updated Shopify settings for {$store->name}.", $previous, $store->only(['name', 'shop_domain', 'country', 'default_language', 'brand_tone', 'status']), $store);

        return back()->with('status', 'Store settings updated.');
    }

    private function createAdminManagedStore(Account $account, User $owner, array $data, ShopifyService $shopify): void
    {
        $domain = $shopify->normalizeDomain($data['store_url']);

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $owner->id,
            'name' => $data['company_name'],
            'shop_domain' => $domain,
            'shop_url' => 'https://'.$domain,
            'country' => $data['store_region'] ?? null,
            'default_language' => $data['store_language'] ?: 'en',
            'brand_tone' => $data['brand_tone'] ?? null,
            'status' => 'pending',
        ]);

        if (filled($data['shopify_access_token'] ?? null) || (filled($data['shopify_api_key'] ?? null) && filled($data['shopify_client_secret'] ?? null))) {
            $store->credential()->create([
                'account_id' => $account->id,
                'admin_api_access_token' => filled($data['shopify_access_token'] ?? null) ? $data['shopify_access_token'] : null,
                'api_key' => filled($data['shopify_api_key'] ?? null) ? $data['shopify_api_key'] : null,
                'client_secret' => filled($data['shopify_client_secret'] ?? null) ? $data['shopify_client_secret'] : null,
            ]);

            try {
                $metadata = $shopify->validateConnection($store->fresh('credential'));
                $store->update([
                    'status' => 'connected',
                    'metadata' => $metadata,
                    'country' => $metadata['shopAddress']['countryCode'] ?? $store->country,
                    'currency' => $metadata['currencyCode'] ?? null,
                    'timezone' => $metadata['ianaTimezone'] ?? null,
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
        }
    }

    private function uniqueAccountSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'account';
        $slug = $base;
        $index = 2;

        while (Account::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }

    private function activity(Request $request, Account $account, string $action, string $entityType, string $status, string $description, ?array $previous = null, ?array $new = null, ?ShopifyStore $store = null): void
    {
        ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $request->user()->id,
            'shopify_store_id' => $store?->id,
            'subject_type' => $store?->getMorphClass() ?? $account->getMorphClass(),
            'subject_id' => $store?->id ?? $account->id,
            'action' => $action,
            'entity_type' => $entityType,
            'status' => $status,
            'description' => $description,
            'previous_values' => $previous,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function summarizeGenerations(Collection $generations, AICostService $costs): array
    {
        return $generations->reduce(function (array $carry, $generation) use ($costs): array {
            $tokens = $costs->tokens($generation->token_usage ?? []);

            return [
                'generations' => $carry['generations'] + 1,
                'input_tokens' => $carry['input_tokens'] + $tokens['input'],
                'cached_input_tokens' => $carry['cached_input_tokens'] + $tokens['cached_input'],
                'output_tokens' => $carry['output_tokens'] + $tokens['output'],
                'total_tokens' => $carry['total_tokens'] + $tokens['total'],
                'estimated_cost' => round($carry['estimated_cost'] + $costs->costForGeneration($generation), 4),
            ];
        }, [
            'generations' => 0,
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'estimated_cost' => 0.0,
        ]);
    }
}
