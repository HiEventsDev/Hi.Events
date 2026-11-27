<?php

namespace Tests\Feature\Http\Actions\TicketResale;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class TicketResaleSettingsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private array $headers;

    private int $organizerId;

    private int $eventId;

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $this->accountId] = $this->createAccountOwner();
        $this->headers = $this->headersFor($owner, $this->accountId);

        $this->organizerId = $this->postJson('/organizers', [
            'name' => 'Resale Organizer',
            'email' => 'resale@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ], $this->headers)->assertCreated()->json('data.id');

        $this->eventId = $this->createEvent('Original');
    }

    public function test_resale_is_off_with_a_two_hour_cutoff_until_the_organizer_changes_it(): void
    {
        $settings = $this->getJson("/events/{$this->eventId}/settings", $this->headers)->assertOk();

        $settings->assertJsonPath('data.allow_ticket_resale', false);
        $settings->assertJsonPath('data.ticket_resale_cutoff_minutes', 120);
        $this->getJson("/public/events/{$this->eventId}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.settings.allow_ticket_resale', false);
    }

    public function test_an_organizer_saves_resale_settings_and_an_unrelated_save_keeps_them(): void
    {
        $this->patchJson("/events/{$this->eventId}/settings", [
            'allow_ticket_resale' => true,
            'ticket_resale_cutoff_minutes' => 1440,
        ], $this->headers)->assertOk();

        $this->patchJson("/events/{$this->eventId}/settings", ['support_email' => 'help@example.test'], $this->headers)->assertOk();

        $this->assertDatabaseHas('event_settings', [
            'event_id' => $this->eventId,
            'allow_ticket_resale' => true,
            'ticket_resale_cutoff_minutes' => 1440,
        ]);
        $this->getJson("/public/events/{$this->eventId}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.settings.allow_ticket_resale', true);
    }

    public function test_a_negative_resale_cutoff_is_refused(): void
    {
        $this->patchJson("/events/{$this->eventId}/settings", ['ticket_resale_cutoff_minutes' => -5], $this->headers)
            ->assertJsonValidationErrors(['ticket_resale_cutoff_minutes']);
    }

    public function test_the_organizer_default_decides_whether_new_events_allow_resale(): void
    {
        $this->patchJson("/organizers/{$this->organizerId}/settings", ['default_allow_ticket_resale' => true], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.default_allow_ticket_resale', true);

        $this->assertDatabaseHas('event_settings', ['event_id' => $this->createEvent('Spring Gala'), 'allow_ticket_resale' => true]);
        $this->assertDatabaseHas('event_settings', ['event_id' => $this->eventId, 'allow_ticket_resale' => false]);
    }

    public function test_only_members_who_can_issue_refunds_can_turn_resale_on_or_off(): void
    {
        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['ISSUE_REFUNDS' => false]);
        $memberHeaders = $this->headersFor($member, $this->accountId);

        $this->patchJson("/events/{$this->eventId}/settings", ['allow_ticket_resale' => true], $memberHeaders)
            ->assertJsonValidationErrors(['allow_ticket_resale']);
        $this->patchJson("/organizers/{$this->organizerId}/settings", ['default_allow_ticket_resale' => true], $memberHeaders)
            ->assertJsonValidationErrors(['default_allow_ticket_resale']);

        $this->patchJson("/events/{$this->eventId}/settings", [
            'allow_ticket_resale' => false,
            'ticket_resale_cutoff_minutes' => 60,
        ], $memberHeaders)->assertOk();
        $this->patchJson("/organizers/{$this->organizerId}/settings", ['default_allow_ticket_resale' => false], $memberHeaders)
            ->assertOk();

        $this->assertDatabaseHas('event_settings', ['event_id' => $this->eventId, 'allow_ticket_resale' => false, 'ticket_resale_cutoff_minutes' => 60]);
        $this->assertDatabaseHas('organizer_settings', ['organizer_id' => $this->organizerId, 'default_allow_ticket_resale' => false]);

        $this->forgetAuthenticatedUser();
        $this->patchJson("/events/{$this->eventId}/settings", ['allow_ticket_resale' => true], $this->headers)->assertOk();

        $this->forgetAuthenticatedUser();
        $this->patchJson("/events/{$this->eventId}/settings", ['allow_ticket_resale' => false], $memberHeaders)
            ->assertJsonValidationErrors(['allow_ticket_resale']);
    }

    public function test_a_full_settings_save_without_resale_cannot_turn_it_off_for_a_member_who_cannot_issue_refunds(): void
    {
        $this->patchJson("/events/{$this->eventId}/settings", ['allow_ticket_resale' => true], $this->headers)->assertOk();
        $settings = $this->getJson("/events/{$this->eventId}/settings", $this->headers)->assertOk()->json('data');
        unset($settings['allow_ticket_resale']);

        $member = $this->addAccountUser($this->accountId);
        $this->setPermissionOverrides($member, $this->accountId, ['ISSUE_REFUNDS' => false]);

        $this->forgetAuthenticatedUser();
        $this->putJson("/events/{$this->eventId}/settings", $settings, $this->headersFor($member, $this->accountId))
            ->assertJsonValidationErrors(['allow_ticket_resale']);

        $this->assertDatabaseHas('event_settings', ['event_id' => $this->eventId, 'allow_ticket_resale' => true]);
    }

    public function test_a_ticket_type_can_be_marked_as_not_resellable(): void
    {
        $payload = [
            'title' => 'VIP',
            'type' => 'FREE',
            'product_type' => 'TICKET',
            'product_category_id' => DB::table('product_categories')->insertGetId([
                'event_id' => $this->eventId,
                'name' => 'Tickets',
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'prices' => [['price' => 0]],
        ];

        $productId = $this->postJson("/events/{$this->eventId}/products", $payload, $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.is_resellable', true)
            ->json('data.id');

        $this->putJson("/events/{$this->eventId}/products/$productId", [...$payload, 'is_resellable' => false], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.is_resellable', false);
        $this->assertDatabaseHas('products', ['id' => $productId, 'is_resellable' => false]);
    }

    public function test_the_resale_events_are_accepted_as_webhook_event_types(): void
    {
        $this->postJson("/events/{$this->eventId}/webhooks", [
            'event_types' => ['attendee.resale_listed', 'attendee.resold', 'attendee.unknown'],
        ], $this->headers)
            ->assertJsonValidationErrors(['event_types.2'])
            ->assertJsonMissingValidationErrors(['event_types.0', 'event_types.1']);
    }

    private function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }

    private function createEvent(string $title): int
    {
        return $this->postJson('/events', [
            'title' => $title,
            'organizer_id' => $this->organizerId,
            'start_date' => now()->addMonth()->format('Y-m-d H:i'),
            'timezone' => 'UTC',
            'currency' => 'USD',
        ], $this->headers)->assertOk()->json('data.id');
    }
}
