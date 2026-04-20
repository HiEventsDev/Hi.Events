<?php

namespace Tests\Unit\Resources\Webhook;

use HiEvents\DomainObjects\WebhookDomainObject;
use HiEvents\Resources\Webhook\WebhookResource;
use HiEvents\Resources\Webhook\WebhookResourceWithSecret;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebhookResourceTest extends TestCase
{
    private function createWebhookDomainObject(): WebhookDomainObject
    {
        return (new WebhookDomainObject())
            ->setId(1)
            ->setUrl('https://example.com/webhook')
            ->setEventTypes(['order.created', 'attendee.created'])
            ->setStatus('ENABLED')
            ->setSecret('test-secret-key-1234567890abcdef');
    }

    public function test_webhook_resource_excludes_secret(): void
    {
        $webhook = $this->createWebhookDomainObject();
        $resource = (new WebhookResource($webhook))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('secret', $resource);
        $this->assertEquals(1, $resource['id']);
        $this->assertEquals('https://example.com/webhook', $resource['url']);
        $this->assertEquals('ENABLED', $resource['status']);
    }

    public function test_webhook_resource_with_secret_includes_secret(): void
    {
        $webhook = $this->createWebhookDomainObject();
        $resource = (new WebhookResourceWithSecret($webhook))->toArray(Request::create('/'));

        $this->assertArrayHasKey('secret', $resource);
        $this->assertEquals('test-secret-key-1234567890abcdef', $resource['secret']);
    }

    public function test_webhook_resource_with_secret_includes_all_base_fields(): void
    {
        $webhook = $this->createWebhookDomainObject();
        $resource = (new WebhookResourceWithSecret($webhook))->toArray(Request::create('/'));

        $this->assertEquals(1, $resource['id']);
        $this->assertEquals('https://example.com/webhook', $resource['url']);
        $this->assertEquals(['order.created', 'attendee.created'], $resource['event_types']);
        $this->assertEquals('ENABLED', $resource['status']);
        $this->assertEquals('test-secret-key-1234567890abcdef', $resource['secret']);
    }
}
