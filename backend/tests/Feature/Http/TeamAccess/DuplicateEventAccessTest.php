<?php

namespace Tests\Feature\Http\TeamAccess;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class DuplicateEventAccessTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $this->accountId] = $this->createAccountOwner();
        $ownerHeaders = $this->headersFor($owner, $this->accountId);

        $this->organizerId = $this->postJson('/organizers', [
            'name' => 'Organizer A',
            'email' => 'organizer-a@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ], $ownerHeaders)->assertCreated()->json('data.id');

        $this->eventId = $this->postJson('/events', [
            'title' => 'Original',
            'organizer_id' => $this->organizerId,
            'start_date' => now()->addMonth()->format('Y-m-d H:i'),
            'timezone' => 'UTC',
            'currency' => 'USD',
        ], $ownerHeaders)->assertOk()->json('data.id');
    }

    public function test_duplicating_an_event_requires_an_organizer_grant(): void
    {
        $eventOnly = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventId]);
        $organizerGrant = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerId]);
        $eventCount = DB::table('events')->count();

        $this->postJson("/events/{$this->eventId}/lineup/spaces", ['name' => 'Main Stage'], $this->headersFor($eventOnly, $this->accountId))
            ->assertCreated();

        $this->postJson("/events/{$this->eventId}/duplicate", $this->payload(), $this->headersFor($eventOnly, $this->accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'ACCESS_RESTRICTED');

        $this->assertSame($eventCount, DB::table('events')->count());

        $copyId = $this->postJson("/events/{$this->eventId}/duplicate", $this->payload(), $this->headersFor($organizerGrant, $this->accountId))
            ->assertOk()
            ->assertJsonPath('data.organizer_id', $this->organizerId)
            ->assertJsonPath('data.title', 'Copy')
            ->json('data.id');

        $this->assertNotSame($this->eventId, $copyId);
        $this->assertSame($eventCount + 1, DB::table('events')->count());
    }

    private function payload(): array
    {
        return [
            'title' => 'Copy',
            'start_date' => now()->addMonths(2)->format('Y-m-d H:i'),
            'duplicate_products' => true,
            'duplicate_questions' => true,
            'duplicate_settings' => true,
            'duplicate_promo_codes' => true,
            'duplicate_capacity_assignments' => true,
            'duplicate_check_in_lists' => true,
            'duplicate_event_cover_image' => true,
            'duplicate_webhooks' => true,
            'duplicate_affiliates' => true,
            'duplicate_lineup' => true,
            'duplicate_ticket_logo' => true,
            'duplicate_occurrences' => true,
        ];
    }
}
