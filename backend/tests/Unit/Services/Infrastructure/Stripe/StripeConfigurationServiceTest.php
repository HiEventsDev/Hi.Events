<?php

namespace Tests\Unit\Services\Infrastructure\Stripe;

use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use Tests\TestCase;

class StripeConfigurationServiceTest extends TestCase
{
    private StripeConfigurationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StripeConfigurationService();
    }

    public function test_get_secret_key_returns_default_when_no_platform(): void
    {
        config(['services.stripe.secret_key' => 'sk_default']);
        
        $result = $this->service->getSecretKey();
        
        $this->assertEquals('sk_default', $result);
    }

    public function test_get_secret_key_returns_canada_platform_key(): void
    {
        config([
            'services.stripe.secret_key' => 'sk_default',
            'services.stripe.ca_secret_key' => 'sk_canada'
        ]);
        
        $result = $this->service->getSecretKey(StripePlatform::CANADA);
        
        $this->assertEquals('sk_canada', $result);
    }

    public function test_get_secret_key_returns_ireland_platform_key(): void
    {
        config([
            'services.stripe.secret_key' => 'sk_default',
            'services.stripe.ie_secret_key' => 'sk_ireland'
        ]);
        
        $result = $this->service->getSecretKey(StripePlatform::IRELAND);
        
        $this->assertEquals('sk_ireland', $result);
    }

    public function test_get_secret_key_returns_null_when_no_keys_configured(): void
    {
        // Clear all configuration
        config([
            'services.stripe.secret_key' => null,
            'services.stripe.ca_secret_key' => null,
            'services.stripe.ie_secret_key' => null
        ]);
        
        $result = $this->service->getSecretKey();
        
        $this->assertNull($result);
    }

    public function test_get_public_key_returns_correct_platform_keys(): void
    {
        config([
            'services.stripe.public_key' => 'pk_default',
            'services.stripe.ca_public_key' => 'pk_canada',
            'services.stripe.ie_public_key' => 'pk_ireland'
        ]);
        
        $this->assertEquals('pk_default', $this->service->getPublicKey());
        $this->assertEquals('pk_canada', $this->service->getPublicKey(StripePlatform::CANADA));
        $this->assertEquals('pk_ireland', $this->service->getPublicKey(StripePlatform::IRELAND));
    }

    public function test_get_all_webhook_secrets_includes_all_platforms(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_default',
            'services.stripe.ca_webhook_secret' => 'whsec_canada',
            'services.stripe.ie_webhook_secret' => 'whsec_ireland'
        ]);
        
        $result = $this->service->getAllWebhookSecrets();
        
        $this->assertEquals('whsec_default', $result['default']);
        $this->assertEquals('whsec_canada', $result['ca']);
        $this->assertEquals('whsec_ireland', $result['ie']);
    }

    public function test_get_primary_platform_returns_correct_enum(): void
    {
        config(['services.stripe.primary_platform' => 'ie']);
        
        $result = $this->service->getPrimaryPlatform();
        
        $this->assertEquals(StripePlatform::IRELAND, $result);
    }

    public function test_get_primary_platform_returns_null_when_not_configured(): void
    {
        config(['services.stripe.primary_platform' => null]);
        
        $result = $this->service->getPrimaryPlatform();
        
        $this->assertNull($result);
    }

    public function test_get_primary_platform_returns_null_for_invalid_platform(): void
    {
        config(['services.stripe.primary_platform' => 'invalid']);
        
        $result = $this->service->getPrimaryPlatform();
        
        $this->assertNull($result);
    }

    public function test_get_all_webhook_secrets_returns_filtered_secrets(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_default',
            'services.stripe.ca_webhook_secret' => 'whsec_canada',
            'services.stripe.ie_webhook_secret' => null
        ]);
        
        $result = $this->service->getAllWebhookSecrets();
        
        $expected = [
            'default' => 'whsec_default',
            'ca' => 'whsec_canada'
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function test_get_all_webhook_secrets_orders_primary_platform_first(): void
    {
        config([
            'services.stripe.webhook_secret' => 'whsec_default',
            'services.stripe.ca_webhook_secret' => 'whsec_canada',
            'services.stripe.ie_webhook_secret' => 'whsec_ireland',
            'services.stripe.primary_platform' => 'ie'
        ]);
        
        $result = $this->service->getAllWebhookSecrets();
        
        $keys = array_keys($result);
        $this->assertEquals('ie', $keys[0], 'Primary platform should be first');
    }

    public function test_get_primary_platform_handles_string_conversion(): void
    {
        config(['services.stripe.primary_platform' => 'ca']);
        
        $result = $this->service->getPrimaryPlatform();
        
        $this->assertEquals(StripePlatform::CANADA, $result);
    }
}