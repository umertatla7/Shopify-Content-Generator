<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_are_sent_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_shopify_login_context_redirects_back_into_app_entrypoint(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.manual_connection_mode', false);

        $response = $this->get('/login?shop=acme.myshopify.com&host=encoded-host&embedded=1');

        $response->assertRedirect('/shopify/app?shop=acme.myshopify.com&host=encoded-host&embedded=1');
    }

    public function test_shopify_register_context_redirects_back_into_app_entrypoint(): void
    {
        config()->set('services.shopify.public_app_api_key', 'shopify_key');
        config()->set('services.shopify.manual_connection_mode', false);

        $response = $this->get('/register?shop=acme.myshopify.com&host=encoded-host&embedded=1');

        $response->assertRedirect('/shopify/app?shop=acme.myshopify.com&host=encoded-host&embedded=1');
    }
}
