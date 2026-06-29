<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\KeywordPositionSnapshot;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleProperty;
use App\Models\ShopifyStore;
use App\Models\TrackedKeyword;
use App\Services\Google\SearchConsoleService;
use App\Services\PlanLimitService;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SearchConsoleController extends Controller
{
    public function index(Request $request, SystemSettingService $settings, PlanLimitService $planLimits): Response|RedirectResponse
    {
        if ($request->user()->isPlatformAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $account = $this->account($request);
        $this->authorizeRankTracking($request);

        $properties = SearchConsoleProperty::query()
            ->forAccount($account)
            ->with('store:id,name,shop_domain,shop_url')
            ->orderByDesc('selected')
            ->orderBy('site_url')
            ->get();

        $selectedProperty = $properties->firstWhere('id', (int) $request->input('property_id'))
            ?? $properties->firstWhere('selected', true)
            ?? $properties->first();

        $startDate = $request->date('start_date')?->toDateString()
            ?? now()->subDays(29)->toDateString();
        $endDate = $request->date('end_date')?->toDateString()
            ?? now()->subDays(2)->toDateString();

        $baseQuery = KeywordPositionSnapshot::query()
            ->forAccount($account)
            ->when($selectedProperty, fn ($query) => $query->where('search_console_property_id', $selectedProperty->id))
            ->whereBetween('date', [$startDate, $endDate])
            ->when($request->filled('device'), fn ($query) => $query->where('device', (string) $request->string('device')))
            ->when($request->filled('country'), fn ($query) => $query->where('country', (string) $request->string('country')))
            ->when($request->filled('q'), fn ($query) => $query->where('query', 'like', '%'.((string) $request->string('q')).'%'));

        $summary = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(clicks), 0) as clicks, COALESCE(SUM(impressions), 0) as impressions, AVG(position) as avg_position')
            ->first();

        $rankings = (clone $baseQuery)
            ->selectRaw('query, page, country, device, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position, AVG(ctr) as ctr, MAX(date) as last_seen')
            ->groupBy('query', 'page', 'country', 'device')
            ->orderByDesc(DB::raw('SUM(impressions)'))
            ->paginate(25)
            ->withQueryString()
            ->through(fn (KeywordPositionSnapshot $row): array => [
                'query' => $row->query,
                'page' => $row->page,
                'country' => $row->country,
                'device' => $row->device,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'ctr' => round(((float) $row->ctr) * 100, 2),
                'position' => $row->position ? round((float) $row->position, 2) : null,
                'last_seen' => $row->last_seen,
            ]);

        $topQuery = (clone $baseQuery)
            ->selectRaw('query, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->groupBy('query')
            ->orderByDesc(DB::raw('SUM(clicks)'))
            ->first();

        $topPages = (clone $baseQuery)
            ->selectRaw('page, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->whereNotNull('page')
            ->groupBy('page')
            ->orderByDesc(DB::raw('SUM(clicks)'))
            ->limit(5)
            ->get()
            ->map(fn (KeywordPositionSnapshot $row): array => [
                'page' => $row->page,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position' => $row->position ? round((float) $row->position, 2) : null,
            ]);
        $trackedKeywords = TrackedKeyword::query()
            ->forAccount($account)
            ->with(['store:id,name', 'latestSnapshot'])
            ->where('status', 'active')
            ->latest()
            ->limit(25)
            ->get();

        return Inertia::render('RankTracking/Index', [
            'isConfigured' => $settings->configured('google_search_console_client_id', config('services.google_search_console.client_id'))
                && $settings->configured('google_search_console_client_secret', config('services.google_search_console.client_secret')),
            'connection' => SearchConsoleConnection::query()
                ->forAccount($account)
                ->with('user:id,name,email')
                ->latest()
                ->first(),
            'properties' => $properties,
            'selectedPropertyId' => $selectedProperty?->id,
            'stores' => ShopifyStore::query()
                ->forAccount($account)
                ->orderBy('name')
                ->get(['id', 'name', 'shop_domain', 'shop_url']),
            'filters' => [
                'property_id' => $selectedProperty?->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'device' => $request->input('device', ''),
                'country' => $request->input('country', ''),
                'q' => $request->input('q', ''),
            ],
            'summary' => [
                'tracked_keywords' => $account->trackedKeywords()->where('status', 'active')->count(),
                'clicks' => (int) ($summary->clicks ?? 0),
                'impressions' => (int) ($summary->impressions ?? 0),
                'avg_ctr' => ($summary->impressions ?? 0) > 0
                    ? round(((int) $summary->clicks / (int) $summary->impressions) * 100, 2)
                    : 0,
                'avg_position' => $summary->avg_position ? round((float) $summary->avg_position, 2) : null,
                'top_query' => $topQuery ? [
                    'query' => $topQuery->query,
                    'clicks' => (int) $topQuery->clicks,
                    'impressions' => (int) $topQuery->impressions,
                    'position' => $topQuery->position ? round((float) $topQuery->position, 2) : null,
                ] : null,
            ],
            'topPages' => $topPages,
            'rankings' => $rankings,
            'trackedKeywords' => $trackedKeywords,
            'planUsage' => $planLimits->summary($account),
        ]);
    }

    public function storeTrackedKeyword(Request $request, SearchConsoleService $searchConsole, PlanLimitService $planLimits): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorizeRankTracking($request);

        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'shopify_store_id' => [
                'nullable',
                Rule::exists('shopify_stores', 'id')->where('account_id', $account->id),
            ],
            'target_url' => ['nullable', 'url', 'max:1024'],
            'intent' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = TrackedKeyword::query()
            ->forAccount($account)
            ->where('keyword', Str::lower(trim((string) $validated['keyword'])))
            ->where('status', 'active')
            ->first();

        if (! $existing) {
            $planLimits->ensureWithinLimit($account, 'tracked_keywords');
        }

        $searchConsole->addTrackedKeyword($account, [
            'shopify_store_id' => $validated['shopify_store_id'] ?? null,
            'keyword' => $validated['keyword'],
            'target_url' => $validated['target_url'] ?? null,
            'intent' => $validated['intent'] ?? null,
        ]);

        return back()->with('status', 'Tracked keyword saved.');
    }

    public function destroyTrackedKeyword(Request $request, TrackedKeyword $trackedKeyword): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorizeRankTracking($request);
        abort_unless((int) $trackedKeyword->account_id === (int) $account->id, 403);

        $trackedKeyword->update(['status' => 'archived']);

        return back()->with('status', 'Tracked keyword removed.');
    }

    public function connect(Request $request, SearchConsoleService $searchConsole): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorizeRankTracking($request);

        $state = Str::random(48);

        $request->session()->put('search_console_oauth_state', [
            'state' => $state,
            'account_id' => $account->id,
            'user_id' => $request->user()->id,
            'created_at' => now()->timestamp,
        ]);

        try {
            return redirect()->away($searchConsole->authorizationUrl($state));
        } catch (RuntimeException $exception) {
            return redirect()->route('rank-tracking.index')->withErrors([
                'google' => $exception->getMessage(),
            ]);
        }
    }

    public function callback(Request $request, SearchConsoleService $searchConsole): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorizeRankTracking($request);

        if ($request->filled('error')) {
            return redirect()->route('rank-tracking.index')->withErrors([
                'google' => $request->input('error_description', $request->input('error')),
            ]);
        }

        $state = $request->session()->pull('search_console_oauth_state');

        if (! $state || $state['state'] !== $request->input('state') || (int) $state['account_id'] !== $account->id) {
            return redirect()->route('rank-tracking.index')->withErrors([
                'google' => 'Google Search Console connection expired. Please try again.',
            ]);
        }

        try {
            $connection = $searchConsole->upsertConnection(
                $account,
                $request->user(),
                $searchConsole->exchangeCode((string) $request->input('code'))
            );
            $properties = $searchConsole->syncProperties($connection);

            ActivityLog::query()->create([
                'account_id' => $account->id,
                'user_id' => $request->user()->id,
                'action' => 'search_console.connected',
                'entity_type' => 'search_console',
                'status' => 'success',
                'description' => "Google Search Console connected with {$properties->count()} properties.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('rank-tracking.index')->with('status', 'Google Search Console connected.');
        } catch (RuntimeException $exception) {
            return redirect()->route('rank-tracking.index')->withErrors([
                'google' => $exception->getMessage(),
            ]);
        }
    }

    public function syncProperties(Request $request, SearchConsoleService $searchConsole): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorizeRankTracking($request);
        $connection = SearchConsoleConnection::query()->forAccount($account)->latest()->first();

        if (! $connection) {
            return back()->withErrors(['google' => 'Connect Google Search Console first.']);
        }

        try {
            $properties = $searchConsole->syncProperties($connection);

            return back()->with('status', "Synced {$properties->count()} Search Console properties.");
        } catch (RuntimeException $exception) {
            return back()->withErrors(['google' => $exception->getMessage()]);
        }
    }

    public function updateProperty(Request $request, SearchConsoleProperty $property): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorizeRankTracking($request);
        abort_unless((int) $property->account_id === (int) $account->id, 403);

        $validated = $request->validate([
            'selected' => ['nullable', 'boolean'],
            'shopify_store_id' => [
                'nullable',
                Rule::exists('shopify_stores', 'id')->where('account_id', $account->id),
            ],
        ]);

        if ((bool) ($validated['selected'] ?? false)) {
            SearchConsoleProperty::query()
                ->forAccount($account)
                ->whereKeyNot($property->id)
                ->update(['selected' => false]);
        }

        $property->update([
            'selected' => (bool) ($validated['selected'] ?? $property->selected),
            'shopify_store_id' => $validated['shopify_store_id'] ?? $property->shopify_store_id,
        ]);

        return back()->with('status', 'Search Console property updated.');
    }

    public function syncPerformance(Request $request, SearchConsoleService $searchConsole): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorizeRankTracking($request);

        $validated = $request->validate([
            'property_id' => [
                'nullable',
                Rule::exists('search_console_properties', 'id')->where('account_id', $account->id),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $property = SearchConsoleProperty::query()
            ->forAccount($account)
            ->when($validated['property_id'] ?? null, fn ($query, $propertyId) => $query->whereKey($propertyId))
            ->when(! ($validated['property_id'] ?? null), fn ($query) => $query->where('selected', true))
            ->first();

        if (! $property) {
            return back()->withErrors(['google' => 'Select a Search Console property first.']);
        }

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : now()->subDays(2);
        $startDate = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : $endDate->copy()->subDays(27);

        try {
            $seeded = $searchConsole->seedTrackedKeywords($account);
            $rows = $searchConsole->syncSearchAnalytics($property, $startDate, $endDate);

            ActivityLog::query()->create([
                'account_id' => $account->id,
                'user_id' => $request->user()->id,
                'shopify_store_id' => $property->shopify_store_id,
                'subject_type' => $property->getMorphClass(),
                'subject_id' => $property->id,
                'action' => 'search_console.performance_synced',
                'entity_type' => 'search_console',
                'status' => 'success',
                'description' => "Imported {$rows} Search Console ranking rows.",
                'new_values' => [
                    'rows' => $rows,
                    'seeded_keywords' => $seeded,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->with('status', "Search Console synced: {$rows} rows imported, {$seeded} generated keywords added.");
        } catch (RuntimeException $exception) {
            return back()->withErrors(['google' => $exception->getMessage()]);
        }
    }

    private function account(Request $request): Account
    {
        $account = $request->user()?->currentAccount;

        abort_unless($account, 403);

        return $account;
    }

    private function authorizeRankTracking(Request $request): void
    {
        abort_unless($request->user()?->hasAccountPermission('stores.view'), 403);
    }
}
