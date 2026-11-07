<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Organizers\CreateOrganizerAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\CreateOrganizerTool;
use Laravel\Mcp\Request;

class CreateOrganizerToolTest extends McpToolTestCase
{
    public function test_it_sends_only_organizer_fields_and_returns_a_compact_organizer(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateOrganizerAction::class, [], [
                'name' => 'Acme Events',
                'email' => 'hello@acme.test',
                'timezone' => 'Europe/Dublin',
                'currency' => 'EUR',
            ])
            ->andReturn($this->ok([
                'id' => 9, 'name' => 'Acme Events', 'email' => 'hello@acme.test', 'timezone' => 'Europe/Dublin',
                'currency' => 'EUR', 'status' => 'DRAFT', 'slug' => 'acme-events',
            ], ResponseCodes::HTTP_CREATED));

        $result = $this->structured((new CreateOrganizerTool($this->api))->handle(new Request([
            'name' => 'Acme Events', 'email' => 'hello@acme.test', 'timezone' => 'Europe/Dublin', 'currency' => 'EUR',
            'account_id' => 999,
        ])));

        $this->assertSame([
            'id' => 9, 'name' => 'Acme Events', 'email' => 'hello@acme.test',
            'timezone' => 'Europe/Dublin', 'currency' => 'EUR', 'status' => 'DRAFT',
        ], $result['organizer']);
    }

    public function test_validation_errors_come_back_field_by_field(): void
    {
        $this->api->shouldReceive('send')->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, [
            'errors' => ['currency' => ['The selected currency is invalid.']],
        ]));

        $this->assertSame(
            'currency: The selected currency is invalid.',
            $this->errorText((new CreateOrganizerTool($this->api))->handle(new Request(['name' => 'X', 'currency' => 'XXX']))),
        );
    }
}
