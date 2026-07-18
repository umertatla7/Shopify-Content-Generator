<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Blog;
use App\Models\Product;
use App\Models\ShopifyStore;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizer_removes_executable_html_and_preserves_safe_content(): void
    {
        $clean = HtmlSanitizer::clean('<h2 onclick="alert(1)">Title</h2><script>alert(1)</script><p>Safe <strong>copy</strong>.</p><a href="javascript:alert(1)">Bad</a><a href="https://example.com" target="_blank">Good</a><img src="data:text/html,bad" onerror="alert(1)">');

        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('<strong>copy</strong>', $clean);
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('data:text', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
    }

    public function test_html_models_sanitize_on_write_and_read(): void
    {
        [$account, $store] = $this->accountAndStore();
        $payload = '<p>Useful</p><img src=x onerror=alert(1)><iframe src="https://evil.example"></iframe>';

        $product = Product::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'title' => 'Product',
            'description' => $payload,
            'generated_description' => $payload,
        ]);
        $blog = Blog::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'title' => 'Blog',
            'body' => $payload,
            'status' => Blog::STATUS_DRAFT,
        ]);

        foreach ([$product->description, $product->generated_description, $blog->body] as $html) {
            $this->assertStringContainsString('<p>Useful</p>', $html);
            $this->assertStringNotContainsString('onerror', $html);
            $this->assertStringNotContainsString('iframe', $html);
        }
    }

    public function test_web_responses_include_embedded_safe_security_headers(): void
    {
        $this->get('/privacy-policy')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors https://admin.shopify.com https://*.myshopify.com; base-uri 'self'; object-src 'none'");
    }

    private function accountAndStore(): array
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Content Store',
            'slug' => 'content-store',
            'plan_key' => 'starter',
        ]);
        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'Content Store',
            'shop_domain' => 'content-store.myshopify.com',
            'shop_url' => 'https://content-store.myshopify.com',
            'status' => 'connected',
        ]);

        return [$account, $store];
    }
}
