<?php

namespace Tests\Feature\Mcp;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\Http\Actions\Organizers\CreateOrganizerAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteDispatcher;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\CallsMcpTools;
use Tests\Concerns\UsesApiKeys;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use ActsAsAccountUsers;
    use CallsMcpTools;
    use DatabaseTransactions;
    use UsesApiKeys;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId, 'Own Organizer');
    }

    public function test_every_tool_has_a_title_and_exactly_one_safety_hint(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $tools = $this->mcp($apiKey, 'tools/list')->assertOk()->json('result.tools');

        $this->assertCount(52, $tools);

        foreach ($tools as $tool) {
            $this->assertLessThanOrEqual(64, strlen($tool['name']));
            $this->assertNotEmpty($tool['annotations']['title'], $tool['name']);
            $this->assertTrue(
                ($tool['annotations']['readOnlyHint'] ?? false) xor ($tool['annotations']['destructiveHint'] ?? false),
                $tool['name'],
            );
        }
    }

    public function test_list_organizers_only_returns_the_keys_account(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $this->createOrganizer($otherAccountId, 'Someone Else');
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->callTool($apiKey, 'list_organizers')
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.organizers.0.id', $this->organizerId)
            ->assertJsonCount(1, 'result.structuredContent.organizers');
    }

    public function test_get_event_returns_the_event_with_links(): void
    {
        $eventId = $this->createEvent($this->accountId, $this->organizerId, 'Own Event');
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $this->callTool($apiKey, 'get_event', ['event_id' => $eventId])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.event.id', $eventId)
            ->assertJsonPath('result.structuredContent.event.title', 'Own Event')
            ->assertJsonPath('result.structuredContent.event.links.preview', rtrim(config('app.frontend_url'), '/')."/event/$eventId/preview");
    }

    public function test_get_event_refuses_an_event_in_another_account(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $otherEventId = $this->createEvent($otherAccountId, $this->createOrganizer($otherAccountId), 'Not Yours');
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $response = $this->callTool($apiKey, 'get_event', ['event_id' => $otherEventId])
            ->assertOk()
            ->assertJsonPath('result.isError', true);

        $this->assertStringNotContainsString('Not Yours', $response->getContent());
    }

    public function test_read_only_keys_can_call_read_tools(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId, ApiKeyAccess::READ_ONLY);

        $this->callTool($apiKey, 'list_organizers')
            ->assertOk()
            ->assertJsonPath('result.isError', false);
    }

    public function test_requests_without_a_key_are_refused_with_a_bearer_challenge(): void
    {
        $this->postJson('/mcp', $this->rpc('tools/list'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_a_session_token_cannot_call_the_mcp_server(): void
    {
        $this->postJson('/mcp', $this->rpc('tools/list'), $this->headersFor($this->owner, $this->accountId))
            ->assertUnauthorized();
    }

    public function test_requests_from_an_unknown_origin_are_refused(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        config(['cors.allowed_origins' => ['https://app.example.test']]);

        $this->mcp($apiKey, 'tools/list', [], ['Origin' => 'https://evil.example'])->assertForbidden();
        $this->mcp($apiKey, 'tools/list', [], ['Origin' => 'https://app.example.test'])->assertOk();
    }

    public function test_dispatched_writes_keep_read_only_enforcement(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId, ApiKeyAccess::READ_ONLY);

        $response = $this->dispatcherFor($apiKey)->send('POST', CreateOrganizerAction::class, [], $this->organizerPayload());

        $this->assertSame(ResponseCodes::HTTP_FORBIDDEN, $response->status);
        $this->assertSame('API_KEY_READ_ONLY', $response->body['error_code']);
        $this->assertDatabaseMissing('organizers', ['name' => 'Created over MCP']);
    }

    public function test_dispatched_writes_run_validation_and_persist(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);

        $invalid = $this->dispatcherFor($apiKey)->send('POST', CreateOrganizerAction::class, [], ['name' => 'Missing fields']);
        $this->assertSame(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, $invalid->status);
        $this->assertArrayHasKey('email', $invalid->body['errors']);

        $created = $this->dispatcherFor($apiKey)->send('POST', CreateOrganizerAction::class, [], $this->organizerPayload());
        $this->assertTrue($created->isSuccessful());
        $this->assertDatabaseHas('organizers', ['name' => 'Created over MCP', 'account_id' => $this->accountId]);
    }

    public function test_the_outer_request_is_restored_after_a_dispatch(): void
    {
        $apiKey = $this->createApiKey($this->owner, $this->accountId);
        $dispatcher = $this->dispatcherFor($apiKey);
        $outerRequest = $this->app->make('request');

        $dispatcher->send('POST', CreateOrganizerAction::class, [], $this->organizerPayload());

        $this->assertSame($outerRequest, $this->app->make('request'));
    }

    private function dispatcherFor(string $apiKey): ApiRouteDispatcher
    {
        $this->app['auth']->forgetGuards();
        $this->app->instance('request', Request::create('/mcp', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$apiKey,
            'REMOTE_ADDR' => '198.51.100.7',
        ]));

        return $this->app->make(ApiRouteDispatcher::class);
    }

    private function organizerPayload(): array
    {
        return [
            'name' => 'Created over MCP',
            'email' => 'mcp@example.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ];
    }
}
