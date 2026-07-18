<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ShopifyCredential;
use App\Models\ShopifyStore;
use App\Models\ShopifyWebhookDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopifyWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.shopify.public_app_client_secret', 'webhook-secret');
    }

    public function test_invalid_hmac_is_rejected(): void
    {
        $this->withHeaders($this->headers('app/uninstalled', '{}', hmac: 'invalid'))
            ->postJson('/api/webhooks/shopify', [])
            ->assertUnauthorized();

        $this->assertDatabaseCount('shopify_webhook_deliveries', 0);
    }

    public function test_uninstall_webhook_revokes_credentials_and_is_idempotent(): void
    {
        $store = $this->store();
        $payload = json_encode(['id' => 123]);
        $webhookId = (string) Str::uuid();
        $headers = $this->headers('app/uninstalled', $payload, $webhookId);

        $this->call('POST', '/api/webhooks/shopify', [], [], [], $this->serverHeaders($headers), $payload)
            ->assertOk()
            ->assertJson(['status' => 'processed']);

        $this->assertDatabaseMissing('shopify_credentials', ['shopify_store_id' => $store->id]);
        $this->assertDatabaseHas('shopify_stores', ['id' => $store->id, 'status' => 'disconnected']);

        $this->call('POST', '/api/webhooks/shopify', [], [], [], $this->serverHeaders($headers), $payload)
            ->assertOk()
            ->assertJson(['status' => 'already_processed']);

        $this->assertDatabaseCount('shopify_webhook_deliveries', 1);
    }

    public function test_privacy_webhooks_are_accepted_without_persisting_customer_payload(): void
    {
        $payload = json_encode([
            'shop_id' => 1,
            'customer' => ['id' => 55, 'email' => 'private@example.com'],
        ]);

        $response = $this->call(
            'POST',
            '/api/webhooks/shopify',
            [],
            [],
            [],
            $this->serverHeaders($this->headers('customers/data_request', $payload)),
            $payload,
        );

        $response->assertOk();
        $delivery = ShopifyWebhookDelivery::query()->firstOrFail();
        $this->assertSame(hash('sha256', $payload), $delivery->payload_hash);
        $this->assertStringNotContainsString('private@example.com', json_encode($delivery->toArray()));
    }

    public function test_shop_redact_deletes_the_store_and_cascaded_shop_data(): void
    {
        $store = $this->store();
        $accountId = $store->account_id;
        $userId = $store->connected_by;
        $payload = json_encode(['shop_id' => 123, 'shop_domain' => $store->shop_domain]);

        $this->call(
            'POST',
            '/api/webhooks/shopify',
            [],
            [],
            [],
            $this->serverHeaders($this->headers('shop/redact', $payload)),
            $payload,
        )->assertOk();

        $this->assertDatabaseMissing('shopify_stores', ['id' => $store->id]);
        $this->assertDatabaseMissing('accounts', ['id' => $accountId]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseHas('shopify_webhook_deliveries', ['topic' => 'shop/redact', 'status' => 'processed']);
    }

    private function store(): ShopifyStore
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Webhook Store',
            'slug' => 'webhook-store',
            'plan_key' => 'starter',
        ]);
        $store = ShopifyStore::query()->create([
            'account_id' => $account->id,
            'connected_by' => $user->id,
            'name' => 'Webhook Store',
            'shop_domain' => 'webhook-store.myshopify.com',
            'shop_url' => 'https://webhook-store.myshopify.com',
            'status' => 'connected',
        ]);
        ShopifyCredential::query()->create([
            'account_id' => $account->id,
            'shopify_store_id' => $store->id,
            'admin_api_access_token' => 'token',
        ]);

        return $store;
    }

    private function headers(string $topic, string $payload, ?string $webhookId = null, ?string $hmac = null): array
    {
        return [
            'X-Shopify-Topic' => $topic,
            'X-Shopify-Shop-Domain' => 'webhook-store.myshopify.com',
            'X-Shopify-Webhook-Id' => $webhookId ?? (string) Str::uuid(),
            'X-Shopify-Hmac-Sha256' => $hmac ?? base64_encode(hash_hmac('sha256', $payload, 'webhook-secret', true)),
            'Content-Type' => 'application/json',
        ];
    }

    private function serverHeaders(array $headers): array
    {
        return collect($headers)->mapWithKeys(function (string $value, string $key): array {
            if (strtolower($key) === 'content-type') {
                return ['CONTENT_TYPE' => $value];
            }

            return ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value];
        })->all();
    }
}
