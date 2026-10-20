<?php

namespace Tests\Feature\Http\Actions\Webhooks;

use HiEvents\DomainObjects\Status\WebhookStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class WebhookStatusDefaultTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private string $authToken;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        [$this->user, $this->authToken, $this->accountId] = $this->makeAuthenticatedUser();
        $this->organizerId = $this->makeOrganizer($this->accountId);
        $this->eventId = $this->makeEvent();
    }

    public function test_create_event_webhook_without_status_defaults_to_enabled(): void
    {
        $response = $this->postJson("/events/{$this->eventId}/webhooks", [
            'url' => 'https://example.com/webhook',
            'event_types' => [DomainEventType::ORDER_CREATED->value],
        ], $this->authHeaders());

        $response->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame(WebhookStatus::ENABLED->value, $response->json('data.status'));
    }

    public function test_create_organizer_webhook_without_status_defaults_to_enabled(): void
    {
        $response = $this->postJson("/organizers/{$this->organizerId}/webhooks", [
            'url' => 'https://example.com/webhook',
            'event_types' => [DomainEventType::ORDER_CREATED->value],
        ], $this->authHeaders());

        $response->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame(WebhookStatus::ENABLED->value, $response->json('data.status'));
    }

    public function test_create_event_webhook_with_explicit_status_is_respected(): void
    {
        $response = $this->postJson("/events/{$this->eventId}/webhooks", [
            'url' => 'https://example.com/webhook',
            'event_types' => [DomainEventType::ORDER_CREATED->value],
            'status' => WebhookStatus::PAUSED->value,
        ], $this->authHeaders());

        $response->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame(WebhookStatus::PAUSED->value, $response->json('data.status'));
    }

    public function test_edit_event_webhook_without_status_defaults_to_enabled(): void
    {
        $webhookId = $this->postJson("/events/{$this->eventId}/webhooks", [
            'url' => 'https://example.com/webhook',
            'event_types' => [DomainEventType::ORDER_CREATED->value],
            'status' => WebhookStatus::PAUSED->value,
        ], $this->authHeaders())->json('data.id');

        $response = $this->putJson("/events/{$this->eventId}/webhooks/{$webhookId}", [
            'url' => 'https://example.com/webhook',
            'event_types' => [DomainEventType::ORDER_CREATED->value],
        ], $this->authHeaders());

        $response->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame(WebhookStatus::ENABLED->value, $response->json('data.status'));
    }

    private function makeAuthenticatedUser(): array
    {
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $token = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);

        return [$user, $token, $accountId];
    }

    private function makeOrganizer(int $accountId): int
    {
        return DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Webhook Test Event',
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
