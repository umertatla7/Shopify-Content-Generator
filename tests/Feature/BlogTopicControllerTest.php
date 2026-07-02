<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Blog;
use App\Models\BlogTopic;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\BlogGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTopicControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_blog_creates_draft_immediately_for_an_approved_topic_flow(): void
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_key' => 'free',
        ]);

        $role = Role::query()->create([
            'name' => 'customer_admin',
            'label' => 'Customer Admin',
        ]);

        AccountUser::query()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'accepted_at' => now(),
            'permissions' => ['topics.manage'],
        ]);

        $user->forceFill(['current_account_id' => $account->id])->save();

        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'Acme Store',
            'shop_domain' => 'acme.myshopify.com',
            'shop_url' => 'https://acme.myshopify.com',
            'status' => 'connected',
        ]);

        $topic = BlogTopic::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'title' => 'Best engagement ring styles for first-time buyers',
            'status' => 'waiting',
        ]);

        $this->mock(BlogGenerationService::class, function ($mock) use ($topic, $user, $account, $store): void {
            $mock->shouldReceive('generateFromTopic')
                ->once()
                ->withArgs(fn (BlogTopic $passedTopic, User $passedUser): bool => $passedTopic->is($topic) && $passedUser->is($user))
                ->andReturnUsing(fn () => Blog::query()->create([
                    'account_id' => $account->id,
                    'shopify_store_id' => $store->id,
                    'blog_topic_id' => $topic->id,
                    'generated_by' => $user->id,
                    'title' => $topic->title,
                    'status' => Blog::STATUS_DRAFT,
                    'generation_status' => 'completed',
                ]));
        });

        $response = $this->actingAs($user)->post("/topics/{$topic->id}/generate-blog");

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Blog draft generated.');

        $topic->refresh();

        $this->assertSame('approved', $topic->status);
        $this->assertSame($user->id, $topic->approved_by);
        $this->assertNotNull($topic->approved_at);
        $this->assertDatabaseHas('blogs', [
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'blog_topic_id' => $topic->id,
            'status' => Blog::STATUS_DRAFT,
        ]);
    }
}
