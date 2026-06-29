<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\KeywordPositionSnapshot;
use App\Models\Plan;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleProperty;
use App\Models\TrackedKeyword;
use App\Models\User;
use App\Services\Google\SearchConsoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchConsoleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_search_analytics_only_links_existing_tracked_keywords(): void
    {
        config()->set('services.google_search_console.api_url', 'https://example.test/webmasters/v3');

        $user = User::factory()->create();
        Plan::query()->updateOrCreate([
            'key' => 'free',
        ], [
            'name' => 'Free',
            'monthly_price' => 0,
            'monthly_ai_token_limit' => 1000,
            'monthly_credit_allowance' => 500,
            'store_limit' => 1,
            'is_active' => true,
        ]);
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Moonvera',
            'slug' => 'moonvera',
            'billing_email' => $user->email,
            'timezone' => 'UTC',
            'plan_key' => 'free',
            'credit_balance' => 500,
            'monthly_credit_allowance' => 500,
        ]);
        $connection = SearchConsoleConnection::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'google_email' => $user->email,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
            'status' => 'connected',
        ]);
        $property = SearchConsoleProperty::query()->create([
            'account_id' => $account->id,
            'search_console_connection_id' => $connection->id,
            'site_url' => 'sc-domain:moonvera.com',
            'selected' => true,
        ]);
        $trackedKeyword = TrackedKeyword::query()->create([
            'account_id' => $account->id,
            'keyword' => 'moonstone ring',
            'status' => 'active',
        ]);

        Http::fake([
            'https://example.test/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    [
                        'keys' => ['2026-06-18', 'moonstone ring', 'https://moonvera.com/products/moonstone-ring', 'usa', 'DESKTOP'],
                        'clicks' => 7,
                        'impressions' => 50,
                        'ctr' => 0.14,
                        'position' => 9.2,
                    ],
                    [
                        'keys' => ['2026-06-18', 'untracked phrase', 'https://moonvera.com/products/other', 'usa', 'DESKTOP'],
                        'clicks' => 3,
                        'impressions' => 20,
                        'ctr' => 0.15,
                        'position' => 15.4,
                    ],
                ],
                'responseAggregationType' => 'byPage',
            ], 200),
        ]);

        $stored = app(SearchConsoleService::class)->syncSearchAnalytics($property, '2026-06-01', '2026-06-20');

        $this->assertSame(2, $stored);
        $this->assertSame(1, TrackedKeyword::query()->count());
        $this->assertSame(2, KeywordPositionSnapshot::query()->count());
        $this->assertSame($trackedKeyword->id, KeywordPositionSnapshot::query()->where('query', 'moonstone ring')->value('tracked_keyword_id'));
        $this->assertNull(KeywordPositionSnapshot::query()->where('query', 'untracked phrase')->value('tracked_keyword_id'));
    }
}
