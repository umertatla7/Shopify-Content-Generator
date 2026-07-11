<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPageControllerTest extends TestCase
{
    public function test_privacy_page_is_publicly_available(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertOk();
        $response->assertSee('Privacy Policy');
        $response->assertSee('Deletion and access requests');
    }

    public function test_terms_page_is_publicly_available(): void
    {
        $response = $this->get('/terms-of-service');

        $response->assertOk();
        $response->assertSee('Terms of Service');
        $response->assertSee('Billing');
    }

    public function test_support_page_is_publicly_available(): void
    {
        $response = $this->get('/support');

        $response->assertOk();
        $response->assertSee('Support');
        $response->assertSee('Support channels');
    }

    public function test_shopify_guides_are_publicly_available(): void
    {
        $this->get('/shopify/billing-guide')
            ->assertOk()
            ->assertSee('Shopify Billing Guide');

        $this->get('/shopify/review-guide')
            ->assertOk()
            ->assertSee('Shopify Review Guide');
    }
}
