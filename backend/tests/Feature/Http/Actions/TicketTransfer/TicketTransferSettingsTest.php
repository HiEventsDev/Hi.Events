<?php

namespace Tests\Feature\Http\Actions\TicketTransfer;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class TicketTransferSettingsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private array $headers;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $accountId] = $this->createAccountOwner();
        $this->headers = $this->headersFor($owner, $accountId);

        $this->organizerId = $this->postJson('/organizers', [
            'name' => 'Transfer Organizer',
            'email' => 'transfers@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ], $this->headers)->assertCreated()->json('data.id');

        $this->eventId = $this->createEvent('Original');
    }

    public function test_transfers_are_on_with_no_cutoff_or_limit_until_the_organizer_changes_them(): void
    {
        $settings = $this->getJson("/events/{$this->eventId}/settings", $this->headers)->assertOk();

        $settings->assertJsonPath('data.allow_ticket_transfer', true);
        $settings->assertJsonPath('data.ticket_transfer_cutoff_minutes', 0);
        $settings->assertJsonPath('data.max_transfers_per_ticket', null);
    }

    public function test_an_organizer_saves_transfer_settings_and_an_unrelated_save_keeps_them(): void
    {
        $this->patchJson("/events/{$this->eventId}/settings", [
            'allow_ticket_transfer' => false,
            'ticket_transfer_cutoff_minutes' => 120,
            'max_transfers_per_ticket' => 2,
        ], $this->headers)->assertOk();

        $this->assertDatabaseHas('event_settings', [
            'event_id' => $this->eventId,
            'allow_ticket_transfer' => false,
            'ticket_transfer_cutoff_minutes' => 120,
            'max_transfers_per_ticket' => 2,
        ]);

        $this->patchJson("/events/{$this->eventId}/settings", ['max_transfers_per_ticket' => null], $this->headers)->assertOk();
        $this->patchJson("/events/{$this->eventId}/settings", ['support_email' => 'help@example.test'], $this->headers)->assertOk();

        $this->assertDatabaseHas('event_settings', [
            'event_id' => $this->eventId,
            'allow_ticket_transfer' => false,
            'ticket_transfer_cutoff_minutes' => 120,
            'max_transfers_per_ticket' => null,
        ]);
        $this->getJson("/public/events/{$this->eventId}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.settings.allow_ticket_transfer', false);
    }

    public function test_out_of_range_transfer_settings_are_refused(): void
    {
        $this->patchJson("/events/{$this->eventId}/settings", ['ticket_transfer_cutoff_minutes' => -5], $this->headers)
            ->assertJsonValidationErrors(['ticket_transfer_cutoff_minutes']);
        $this->patchJson("/events/{$this->eventId}/settings", ['max_transfers_per_ticket' => 0], $this->headers)
            ->assertJsonValidationErrors(['max_transfers_per_ticket']);
    }

    public function test_the_organizer_default_decides_whether_new_events_allow_transfers(): void
    {
        $this->patchJson("/organizers/{$this->organizerId}/settings", ['default_allow_ticket_transfer' => false], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.default_allow_ticket_transfer', false);

        $this->assertDatabaseHas('event_settings', ['event_id' => $this->createEvent('Spring Gala'), 'allow_ticket_transfer' => false]);
        $this->assertDatabaseHas('event_settings', ['event_id' => $this->eventId, 'allow_ticket_transfer' => true]);
    }

    public function test_a_ticket_type_can_be_marked_as_not_transferable(): void
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
            ->assertJsonPath('data.is_transferable', true)
            ->json('data.id');

        $this->putJson("/events/{$this->eventId}/products/$productId", [...$payload, 'is_transferable' => false], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.is_transferable', false);
        $this->assertDatabaseHas('products', ['id' => $productId, 'is_transferable' => false]);
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
