<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait CallsMcpTools
{
    protected function callTool(string $apiKey, string $name, array $arguments = []): TestResponse
    {
        return $this->mcp($apiKey, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments], ['Mcp-Name' => $name]);
    }

    protected function mcp(string $apiKey, string $method, array $params = [], array $headers = []): TestResponse
    {
        return $this->postJson('/mcp', $this->rpc($method, $params), [
            ...$this->apiKeyHeaders($apiKey),
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-11-25',
            'Mcp-Method' => $method,
            ...$headers,
        ]);
    }

    protected function rpc(string $method, array $params = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params];
    }

    protected function createOrganizerWithTool(string $apiKey): int
    {
        return $this->callTool($apiKey, 'create_organizer', [
            'name' => 'Organizer made over MCP',
            'email' => 'mcp-organizer@example.com',
            'timezone' => 'Europe/Dublin',
            'currency' => 'EUR',
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.organizer.id');
    }

    protected function createEventWithTool(string $apiKey, int $organizerId, string $title = 'Event made over MCP'): int
    {
        return $this->callTool($apiKey, 'create_event', [
            'organizer_id' => $organizerId,
            'title' => $title,
            'start_date' => '2027-03-01 19:00',
        ])->assertJsonPath('result.isError', false)->json('result.structuredContent.event.id');
    }
}
