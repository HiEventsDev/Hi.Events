<?php

namespace Tests\Feature\Http\Actions\CheckInLists\Public;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesBadgeFixtures;
use Tests\TestCase;

class LookupKioskTicketsPublicTest extends TestCase
{
    use CreatesBadgeFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
    }

    public function test_an_attendee_finds_their_ticket_by_email_and_last_name(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $attendee = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId, 'Speaker'), 'Ada', 'Lovelace');
        $listShortId = $this->createKioskList($eventId);

        $this->lookup($listShortId, ' ADA@example.test ', 'lovelace ')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $attendee['public_id'])
            ->assertJsonPath('data.0.first_name', 'Ada')
            ->assertJsonPath('data.0.product_title', 'Speaker')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.last_name');
    }

    public function test_a_wrong_last_name_finds_nothing(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $listShortId = $this->createKioskList($eventId);

        $this->lookup($listShortId, 'ada@example.test', 'Byron')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_this_lists_tickets_and_live_attendees_are_found(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $speakerId = $this->createTicketProduct($eventId, 'Speaker');
        $generalId = $this->createTicketProduct($eventId, 'General');
        $speaker = $this->createBadgeAttendee($eventId, $speakerId, 'Ada', 'Lovelace');
        $this->createBadgeAttendee($eventId, $generalId, 'Ada', 'Lovelace');
        $this->createBadgeAttendee($eventId, $speakerId, 'Ada', 'Lovelace', 'CANCELLED');
        $otherEventId = $this->createBadgeEvent($accountId);
        $this->createBadgeAttendee($otherEventId, $this->createTicketProduct($otherEventId), 'Ada', 'Lovelace');
        $listShortId = $this->createKioskList($eventId);
        DB::table('product_check_in_lists')->insert([
            'product_id' => $speakerId,
            'check_in_list_id' => DB::table('check_in_lists')->where('short_id', $listShortId)->value('id'),
        ]);

        $this->lookup($listShortId, 'ada@example.test', 'Lovelace')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $speaker['public_id']);
    }

    public function test_lookup_is_refused_on_staffed_lists_and_when_switched_off(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $staffedList = $this->createKioskList($eventId, kiosk: false);
        $lookupOffList = $this->createKioskList($eventId, lookupEnabled: false);

        $this->lookup($staffedList, 'ada@example.test', 'Lovelace')->assertNotFound();
        $this->lookup($lookupOffList, 'ada@example.test', 'Lovelace')->assertNotFound();
        $this->getJson("/public/check-in-lists/$lookupOffList")->assertOk()->assertJsonPath('data.kiosk_lookup_enabled', false);
        $this->getJson("/public/check-in-lists/$staffedList")->assertOk()->assertJsonPath('data.kiosk_lookup_enabled', false);
    }

    public function test_expired_kiosk_lists_refuse_lookups(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $listShortId = $this->createKioskList($eventId);
        DB::table('check_in_lists')->where('short_id', $listShortId)->update(['expires_at' => now()->subDay()]);

        $this->lookup($listShortId, 'ada@example.test', 'Lovelace')->assertForbidden();
    }

    public function test_an_email_and_last_name_are_required(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $listShortId = $this->createKioskList($this->createBadgeEvent($accountId));

        $this->postJson("/public/check-in-lists/$listShortId/kiosk/ticket-lookup", ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'last_name']);
    }

    public function test_lookups_are_rate_limited_separately_from_scanning(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $listShortId = $this->createKioskList($this->createBadgeEvent($accountId));
        $statuses = [];

        for ($attempt = 0; $attempt < 31; $attempt++) {
            $statuses[] = $this->lookup($listShortId, "guess$attempt@example.test", 'Lovelace')->status();
        }

        $this->assertNotContains(429, array_slice($statuses, 0, 30));
        $this->assertSame(429, $statuses[30]);
        $this->assertNotSame(429, $this->getJson("/public/check-in-lists/$listShortId")->status());
    }

    public function test_the_lookup_setting_is_saved_on_check_in_lists(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $headers = $this->headersFor($owner, $accountId);

        $listId = $this->postJson("/events/$eventId/check-in-lists", [
            'name' => 'Kiosk',
            'kiosk_mode' => true,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.kiosk_lookup_enabled', true)
            ->json('data.id');

        $this->putJson("/events/$eventId/check-in-lists/$listId", [
            'name' => 'Kiosk',
            'kiosk_mode' => true,
            'kiosk_lookup_enabled' => false,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.kiosk_lookup_enabled', false);

        $this->putJson("/events/$eventId/check-in-lists/$listId", ['name' => 'Kiosk'], $headers)
            ->assertOk()
            ->assertJsonPath('data.kiosk_lookup_enabled', false);
    }

    private function lookup(string $listShortId, string $email, string $lastName): TestResponse
    {
        return $this->postJson("/public/check-in-lists/$listShortId/kiosk/ticket-lookup", [
            'email' => $email,
            'last_name' => $lastName,
        ]);
    }

    private function createKioskList(int $eventId, bool $kiosk = true, bool $lookupEnabled = true): string
    {
        $shortId = 'cil_'.uniqid();
        DB::table('check_in_lists')->insert([
            'short_id' => $shortId,
            'name' => 'Kiosk',
            'event_id' => $eventId,
            'kiosk_mode' => $kiosk,
            'kiosk_lookup_enabled' => $lookupEnabled,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $shortId;
    }
}
