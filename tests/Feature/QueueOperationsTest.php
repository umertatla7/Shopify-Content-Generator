<?php

namespace Tests\Feature;

use App\Jobs\PublishBlogJob;
use App\Jobs\PublishScheduledBlogJob;
use App\Jobs\PublishScheduledBlogsJob;
use App\Models\Account;
use App\Models\Blog;
use App\Models\BlogSchedule;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\BlogPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class QueueOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_publishing_atomically_claims_and_dispatches_each_due_schedule_once(): void
    {
        Queue::fake();
        [, , $schedule] = $this->scheduledBlog();

        (new PublishScheduledBlogsJob)->handle();
        (new PublishScheduledBlogsJob)->handle();

        $this->assertSame('running', $schedule->fresh()->status);
        Queue::assertPushed(PublishScheduledBlogJob::class, 1);
    }

    public function test_stale_schedule_claim_is_recovered_and_dispatched_again(): void
    {
        Queue::fake();
        [, , $schedule] = $this->scheduledBlog();
        $schedule->update([
            'status' => 'running',
            'last_attempt_at' => now()->subMinutes(20),
        ]);

        (new PublishScheduledBlogsJob)->handle();

        $this->assertSame('running', $schedule->fresh()->status);
        Queue::assertPushed(PublishScheduledBlogJob::class, fn (PublishScheduledBlogJob $job): bool => $job->scheduleId === $schedule->id);
    }

    public function test_scheduled_publish_job_marks_schedule_completed_after_success(): void
    {
        [, $blog, $schedule] = $this->scheduledBlog();
        $schedule->update(['status' => 'running', 'last_attempt_at' => now()]);

        $this->mock(BlogPublishingService::class, function (MockInterface $mock) use ($blog): void {
            $mock->shouldReceive('publish')
                ->once()
                ->andReturnUsing(function () use ($blog): Blog {
                    $blog->update(['status' => Blog::STATUS_PUBLISHED]);

                    return $blog->fresh();
                });
        });

        app()->call([new PublishScheduledBlogJob($schedule->id), 'handle']);

        $this->assertSame('completed', $schedule->fresh()->status);
    }

    public function test_final_scheduled_publish_failure_is_visible_on_schedule_and_blog(): void
    {
        [, $blog, $schedule] = $this->scheduledBlog();
        $schedule->update(['status' => 'running', 'last_attempt_at' => now()]);

        (new PublishScheduledBlogJob($schedule->id))->failed(new RuntimeException('Shopify unavailable.'));

        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertSame('Shopify unavailable.', $schedule->fresh()->error_message);
        $this->assertSame(Blog::STATUS_FAILED, $blog->fresh()->status);
    }

    public function test_final_direct_publish_failure_is_visible_on_blog(): void
    {
        [, $blog] = $this->scheduledBlog();

        (new PublishBlogJob($blog->id))->failed(new RuntimeException('Shopify rejected the article.'));

        $this->assertSame(Blog::STATUS_FAILED, $blog->fresh()->status);
        $this->assertSame('Shopify rejected the article.', $blog->fresh()->failure_message);
    }

    public function test_readiness_endpoint_checks_required_operational_heartbeats(): void
    {
        config()->set('operations.health.require_scheduler', true);
        config()->set('operations.health.require_queue_worker', true);
        config()->set('operations.health.require_real_mail', true);
        config()->set('operations.health_token', 'monitor-secret');
        config()->set('mail.default', 'smtp');

        $this->getJson('/api/health/ready')
            ->assertServiceUnavailable()
            ->assertJsonMissingPath('checks');

        Cache::put('operations:scheduler-heartbeat', now(), now()->addMinutes(10));
        Cache::put('operations:queue-worker-heartbeat', now(), now()->addMinutes(10));

        $this->withHeader('X-Health-Token', 'monitor-secret')
            ->getJson('/api/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true);
    }

    private function scheduledBlog(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Schedule Test',
            'slug' => 'schedule-test-'.$owner->id,
            'plan_key' => 'growth',
        ]);
        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $owner->id,
            'name' => 'Schedule Store',
            'shop_domain' => 'schedule-'.$owner->id.'.myshopify.com',
            'shop_url' => 'https://schedule-'.$owner->id.'.myshopify.com',
            'status' => 'connected',
        ]);
        $blog = Blog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'title' => 'Scheduled article',
            'body' => '<p>Publishable content.</p>',
            'status' => Blog::STATUS_SCHEDULED,
        ]);
        $schedule = BlogSchedule::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'blog_id' => $blog->id,
            'scheduled_for' => now()->subMinute(),
            'timezone' => 'UTC',
            'status' => 'pending',
        ]);

        return [$store, $blog, $schedule];
    }
}
