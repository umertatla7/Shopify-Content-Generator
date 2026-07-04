<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\PublishingLog;
use App\Models\ShopifyStore;
use App\Models\ShopifySyncLog;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneOperationalLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_command_deletes_old_operational_logs_and_keeps_recent_rows(): void
    {
        [$user, $account, $store] = $this->makeStoreContext();

        $oldTimestamp = now()->subDays(120);

        $oldActivity = ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'shopify_store_id' => $store->id,
            'action' => 'products.push',
            'entity_type' => 'product',
            'status' => 'failed',
            'created_at' => $oldTimestamp,
            'updated_at' => $oldTimestamp,
        ]);

        $newActivity = ActivityLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'shopify_store_id' => $store->id,
            'action' => 'products.push',
            'entity_type' => 'product',
            'status' => 'success',
        ]);

        $oldPublishing = PublishingLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'action' => 'publish',
            'status' => 'failed',
            'created_at' => $oldTimestamp,
            'updated_at' => $oldTimestamp,
        ]);

        $newPublishing = PublishingLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'action' => 'publish',
            'status' => 'success',
        ]);

        $oldSync = ShopifySyncLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'products',
            'status' => 'failed',
            'created_at' => $oldTimestamp,
            'updated_at' => $oldTimestamp,
        ]);

        $newSync = ShopifySyncLog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'sync_type' => 'products',
            'status' => 'success',
        ]);

        $oldUsage = UsageLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'shopify_store_id' => $store->id,
            'type' => 'credit_usage',
            'quantity' => 10,
            'created_at' => $oldTimestamp,
            'updated_at' => $oldTimestamp,
        ]);

        $newUsage = UsageLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'shopify_store_id' => $store->id,
            'type' => 'credit_usage',
            'quantity' => 2,
        ]);

        $this->artisan('app:prune-logs', ['--days' => 90, '--include-usage' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('activity_logs', ['id' => $oldActivity->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $newActivity->id]);
        $this->assertDatabaseMissing('publishing_logs', ['id' => $oldPublishing->id]);
        $this->assertDatabaseHas('publishing_logs', ['id' => $newPublishing->id]);
        $this->assertDatabaseMissing('shopify_sync_logs', ['id' => $oldSync->id]);
        $this->assertDatabaseHas('shopify_sync_logs', ['id' => $newSync->id]);
        $this->assertDatabaseMissing('usage_logs', ['id' => $oldUsage->id]);
        $this->assertDatabaseHas('usage_logs', ['id' => $newUsage->id]);
    }

    private function makeStoreContext(): array
    {
        $user = User::factory()->create();

        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Support Test',
            'slug' => 'support-test',
            'plan_key' => 'growth',
        ]);

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'Support Test Store',
            'shop_domain' => 'support-test.myshopify.com',
            'shop_url' => 'https://support-test.myshopify.com',
            'status' => 'connected',
        ]);

        return [$user, $account, $store];
    }
}
