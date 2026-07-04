<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Blog;
use App\Models\Role;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Services\BlogPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPublishControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_blog_publish_runs_immediately_when_queue_mode_is_disabled(): void
    {
        config()->set('services.blog_publishing.via_queue', false);

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
            'permissions' => ['blogs.publish'],
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

        $blog = Blog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'title' => 'How to choose a moonstone ring',
            'status' => Blog::STATUS_APPROVED,
            'body' => '<p>Useful publishable body content.</p>',
        ]);

        $this->mock(BlogPublishingService::class, function ($mock) use ($blog): void {
            $mock->shouldReceive('publish')
                ->once()
                ->andReturnUsing(function () use ($blog) {
                    $blog->update([
                        'status' => Blog::STATUS_PUBLISHED,
                    ]);

                    return $blog->fresh();
                });
        });

        $response = $this->actingAs($user)->post("/blogs/{$blog->id}/publish");

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Blog published to Shopify.');
    }
}
