<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Organizers\GetOrganizersAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteDispatcher;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\ListOrganizersTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ListOrganizersToolTest extends TestCase
{
    private ApiRouteDispatcher&MockInterface $api;

    private ListOrganizersTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = Mockery::mock(ApiRouteDispatcher::class);
        $this->tool = new ListOrganizersTool($this->api);
    }

    public function test_it_returns_a_compact_organizer_list(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOrganizersAction::class)
            ->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_OK, body: ['data' => [[
                'id' => 7,
                'name' => 'Acme Events',
                'email' => 'hello@acme.test',
                'timezone' => 'Europe/Dublin',
                'currency' => 'EUR',
                'status' => 'LIVE',
                'description' => 'Not needed by the agent',
            ]]]));

        $response = $this->tool->handle(new Request);

        $this->assertInstanceOf(ResponseFactory::class, $response);
        $this->assertSame([
            'organizers' => [[
                'id' => 7,
                'name' => 'Acme Events',
                'email' => 'hello@acme.test',
                'timezone' => 'Europe/Dublin',
                'currency' => 'EUR',
                'status' => 'LIVE',
            ]],
        ], $response->getStructuredContent());
    }

    public function test_it_reports_validation_errors_field_by_field(): void
    {
        $this->api->shouldReceive('get')->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, body: [
            'message' => 'The given data was invalid.',
            'errors' => ['name' => ['The name field is required.'], 'email' => ['The email must be valid.']],
        ]));

        $response = $this->tool->handle(new Request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isError());
        $this->assertSame("name: The name field is required.\nemail: The email must be valid.", (string) $response->content());
    }

    public function test_it_passes_through_the_api_message_for_access_errors(): void
    {
        $this->api->shouldReceive('get')->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_FORBIDDEN, body: [
            'message' => 'This API key is read-only.',
            'error_code' => 'API_KEY_READ_ONLY',
        ]));

        $response = $this->tool->handle(new Request);

        $this->assertTrue($response->isError());
        $this->assertSame('This API key is read-only.', (string) $response->content());
    }

    public function test_a_missing_resource_reads_as_not_found_in_this_account(): void
    {
        $this->api->shouldReceive('get')->andReturn(new ApiRouteResponseDTO(status: ResponseCodes::HTTP_NOT_FOUND, body: ['message' => 'Resource not found']));

        $response = $this->tool->handle(new Request);

        $this->assertTrue($response->isError());
        $this->assertSame('Not found in this account.', (string) $response->content());
    }
}
