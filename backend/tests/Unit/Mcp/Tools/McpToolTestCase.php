<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteDispatcher;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

abstract class McpToolTestCase extends TestCase
{
    protected ApiRouteDispatcher&MockInterface $api;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'app.frontend_url' => 'https://app.example.test']);

        $this->api = Mockery::mock(ApiRouteDispatcher::class);
    }

    protected function ok(array $data, int $status = ResponseCodes::HTTP_OK): ApiRouteResponseDTO
    {
        return new ApiRouteResponseDTO(status: $status, body: ['data' => $data]);
    }

    protected function failed(int $status, array $body): ApiRouteResponseDTO
    {
        return new ApiRouteResponseDTO(status: $status, body: $body);
    }

    protected function event(array $overrides = []): array
    {
        return [
            'id' => 12,
            'title' => 'Jazz night',
            'status' => 'DRAFT',
            'type' => 'SINGLE',
            'lifecycle_status' => 'UPCOMING',
            'organizer_id' => 3,
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'category' => 'MUSIC',
            'slug' => 'jazz-night',
            'start_date' => '2026-12-12 01:00:00',
            'end_date' => '2026-12-12 04:00:00',
            'next_occurrence_start_date' => '2026-12-12 01:00:00',
            ...$overrides,
        ];
    }

    protected function structured(Response|ResponseFactory $response): array
    {
        $this->assertInstanceOf(ResponseFactory::class, $response, $response instanceof Response ? (string) $response->content() : '');

        return $response->getStructuredContent();
    }

    protected function errorText(Response|ResponseFactory $response): string
    {
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isError());

        return (string) $response->content();
    }
}
