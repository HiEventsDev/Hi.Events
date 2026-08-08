<?php

declare(strict_types=1);

namespace Tests\Feature\OpenApi;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Tests\TestCase;

class OpenApiGenerationTest extends TestCase
{
    public function test_openapi_document_generates_with_expected_paths(): void
    {
        $document = json_decode(
            json_encode(app(Generator::class)(Scramble::getGeneratorConfig('default'))),
            true,
        );

        $this->assertSame('3.1.0', $document['openapi']);
        $this->assertSame(
            trim(file_get_contents(base_path('VERSION'))),
            $document['info']['version'],
        );
        $this->assertStringContainsString('unversioned', $document['info']['description']);

        $this->assertArrayHasKey('/auth/login', $document['paths']);
        $this->assertArrayHasKey('/events', $document['paths']);
        $this->assertArrayHasKey('/events/{eventId}', $document['paths']);
        $this->assertArrayHasKey('/public/events/{eventId}', $document['paths']);
        $this->assertArrayHasKey('/public/events/{eventId}/order', $document['paths']);

        $this->assertArrayNotHasKey('/mail-test', $document['paths']);
        $this->assertEmpty(array_filter(
            array_keys($document['paths']),
            static fn (string $path) => str_starts_with($path, '/admin') || str_contains($path, 'sitemap'),
        ));

        foreach ($document['paths'] as $path => $operations) {
            if (! str_starts_with($path, '/public/')) {
                continue;
            }

            foreach (array_intersect_key($operations, array_flip(['get', 'post', 'put', 'patch', 'delete'])) as $operation) {
                $this->assertStringContainsString('(public)', $operation['summary'], $path);
            }
        }

        $this->assertSame([], $document['paths']['/auth/login']['post']['security']);
        $this->assertSame(
            ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
            $document['components']['securitySchemes']['http'],
        );

        $eventsListSchema = $document['paths']['/events']['get']['responses']['200']['content']['application/json']['schema'];
        $this->assertSame(
            ['data', 'links', 'meta'],
            array_keys($eventsListSchema['properties']),
        );

        $createAffiliate = $document['paths']['/events/{eventId}/affiliates']['post'];
        $this->assertArrayHasKey('201', $createAffiliate['responses']);
        $this->assertArrayHasKey('AffiliateResource', $document['components']['schemas']);
        $this->assertArrayHasKey('CompleteOrderRequest', $document['components']['schemas']);

        $getEvent = $document['paths']['/events/{eventId}']['get']['responses'];
        $this->assertArrayHasKey('403', $getEvent);
        $this->assertArrayHasKey('404', $getEvent);

        $this->assertContains(
            'COMPLETED',
            $document['components']['schemas']['OrderResource']['properties']['status']['enum'],
        );
        $this->assertStringContainsString('Outgoing webhooks', $document['info']['description']);
        foreach (['OrderResource', 'AttendeeResource', 'ProductResource', 'EventResource', 'AttendeeCheckInResource', 'EventOccurrenceResource'] as $webhookPayloadSchema) {
            $this->assertArrayHasKey($webhookPayloadSchema, $document['components']['schemas']);
        }
    }
}
