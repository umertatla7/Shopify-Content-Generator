<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Plan;
use App\Models\TrackedKeyword;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PlanLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_usage_and_blocks_over_limit_actions(): void
    {
        $user = User::factory()->create();
        $plan = Plan::query()->updateOrCreate([
            'key' => 'free',
        ], [
            'name' => 'Free',
            'monthly_price' => 0,
            'monthly_blog_limit' => 1,
            'monthly_topic_limit' => 3,
            'monthly_ai_token_limit' => 1000,
            'monthly_credit_allowance' => 500,
            'store_limit' => 1,
            'product_description_limit' => 2,
            'collection_description_limit' => 0,
            'monthly_seo_report_limit' => 1,
            'monthly_ai_visibility_report_limit' => 1,
            'tracked_keyword_limit' => 1,
            'is_active' => true,
        ]);
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Moonvera',
            'slug' => 'moonvera',
            'billing_email' => $user->email,
            'timezone' => 'UTC',
            'plan_key' => $plan->key,
            'credit_balance' => 500,
            'monthly_credit_allowance' => 500,
        ]);

        UsageLog::query()->create([
            'account_id' => $account->id,
            'type' => 'credit_usage',
            'quantity' => 10,
            'unit' => 'credit',
            'metadata' => ['action' => 'product_content_generation'],
        ]);
        UsageLog::query()->create([
            'account_id' => $account->id,
            'type' => 'credit_usage',
            'quantity' => 10,
            'unit' => 'credit',
            'metadata' => ['action' => 'product_content_generation'],
        ]);
        UsageLog::query()->create([
            'account_id' => $account->id,
            'type' => 'credit_usage',
            'quantity' => 4,
            'unit' => 'credit',
            'metadata' => ['action' => 'topic_generation', 'topic_count' => 2],
        ]);
        TrackedKeyword::query()->create([
            'account_id' => $account->id,
            'keyword' => 'moonstone ring',
            'status' => 'active',
        ]);

        $service = app(PlanLimitService::class);
        $summary = $service->summary($account);

        $this->assertSame(2, $summary['metrics']['product_descriptions']['used']);
        $this->assertSame(0, $summary['metrics']['product_descriptions']['remaining']);
        $this->assertSame(2, $summary['metrics']['topics']['used']);
        $this->assertSame(1, $summary['metrics']['topics']['remaining']);
        $this->assertSame(1, $summary['metrics']['tracked_keywords']['used']);

        $this->expectException(RuntimeException::class);
        $service->ensureWithinLimit($account, 'product_descriptions');
    }

    public function test_it_blocks_when_tracked_keyword_slots_are_full(): void
    {
        $user = User::factory()->create();
        Plan::query()->updateOrCreate([
            'key' => 'free',
        ], [
            'name' => 'Free',
            'monthly_price' => 0,
            'monthly_ai_token_limit' => 1000,
            'monthly_credit_allowance' => 500,
            'store_limit' => 1,
            'tracked_keyword_limit' => 1,
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
        TrackedKeyword::query()->create([
            'account_id' => $account->id,
            'keyword' => 'moonstone ring',
            'status' => 'active',
        ]);

        $this->expectException(RuntimeException::class);
        app(PlanLimitService::class)->ensureWithinLimit($account, 'tracked_keywords');
    }

    public function test_it_blocks_when_topic_limit_is_exceeded(): void
    {
        $user = User::factory()->create();
        Plan::query()->updateOrCreate([
            'key' => 'free',
        ], [
            'name' => 'Free',
            'monthly_price' => 0,
            'monthly_topic_limit' => 3,
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

        UsageLog::query()->create([
            'account_id' => $account->id,
            'type' => 'credit_usage',
            'quantity' => 6,
            'unit' => 'credit',
            'metadata' => ['action' => 'topic_generation', 'topic_count' => 3],
        ]);

        $this->expectException(RuntimeException::class);
        app(PlanLimitService::class)->ensureWithinLimit($account, 'topics', 1);
    }
}
