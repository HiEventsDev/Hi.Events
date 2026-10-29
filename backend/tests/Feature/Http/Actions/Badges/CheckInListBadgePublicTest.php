<?php

namespace Tests\Feature\Http\Actions\Badges;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesBadgeFixtures;
use Tests\TestCase;

class CheckInListBadgePublicTest extends TestCase
{
    use CreatesBadgeFixtures;
    use DatabaseTransactions;

    public function test_a_badge_is_served_and_marked_printed_from_a_list_with_badge_printing(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $productId = $this->createTicketProduct($eventId, 'Speaker');
        $attendee = $this->createBadgeAttendee($eventId, $productId, 'Ada', 'Lovelace');
        $listShortId = $this->createList($eventId, badgePrinting: true);

        $this->getJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge")
            ->assertOk()
            ->assertJsonPath('data.lines.0.text', 'Ada')
            ->assertJsonPath('data.ribbon.label', 'Speaker')
            ->assertJsonPath('data.print_status', 'NOT_PRINTED');

        $this->postJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge/printed")
            ->assertOk()
            ->assertJsonPath('data.print_status', 'PRINTED')
            ->assertJsonPath('data.badge_print_count', 1);
    }

    public function test_the_public_list_carries_the_badge_layout_only_when_printing_is_enabled(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $printingList = $this->createList($eventId, badgePrinting: true);
        $plainList = $this->createList($eventId);

        $layout = $this->getJson("/public/check-in-lists/$printingList")->assertOk()->json('data.badge_design');

        $this->assertSame('CLASSIC', $layout['template']);
        $this->assertFalse($layout['fold_over']);
        $this->assertArrayNotHasKey('lines', $layout);
        $this->assertArrayNotHasKey('ribbons', $layout);
        $this->getJson("/public/check-in-lists/$plainList")->assertOk()->assertJsonMissingPath('data.badge_design');
    }

    public function test_badges_are_not_available_on_lists_without_badge_printing(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $attendee = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $listShortId = $this->createList($eventId);

        $this->getJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge")->assertNotFound();
        $this->postJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge/printed")->assertNotFound();
        $this->assertSame(0, DB::table('attendees')->where('id', $attendee['id'])->value('badge_print_count'));
    }

    public function test_turning_badges_off_for_the_event_stops_door_printing(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId, badgesEnabled: false);
        $attendee = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $listShortId = $this->createList($eventId, badgePrinting: true);

        $this->getJson("/public/check-in-lists/$listShortId")
            ->assertOk()
            ->assertJsonPath('data.badge_printing_enabled', false)
            ->assertJsonMissingPath('data.badge_design');
        $this->getJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge")->assertNotFound();
        $this->postJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge/printed")->assertNotFound();
    }

    public function test_badges_are_scoped_to_the_lists_tickets_and_event(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $speakerId = $this->createTicketProduct($eventId, 'Speaker');
        $generalId = $this->createTicketProduct($eventId, 'General');
        $general = $this->createBadgeAttendee($eventId, $generalId, 'Grace', 'Hopper');
        $cancelled = $this->createBadgeAttendee($eventId, $speakerId, 'Gone', 'Away', 'CANCELLED');
        $otherEventId = $this->createBadgeEvent($accountId);
        $foreign = $this->createBadgeAttendee($otherEventId, $this->createTicketProduct($otherEventId), 'Not', 'Mine');
        $listShortId = $this->createList($eventId, badgePrinting: true);
        DB::table('product_check_in_lists')->insert([
            'product_id' => $speakerId,
            'check_in_list_id' => DB::table('check_in_lists')->where('short_id', $listShortId)->value('id'),
        ]);

        foreach ([$general, $cancelled, $foreign] as $attendee) {
            $this->getJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge")->assertNotFound();
        }
    }

    public function test_expired_lists_refuse_badges(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $attendee = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $listShortId = $this->createList($eventId, badgePrinting: true);
        DB::table('check_in_lists')->where('short_id', $listShortId)->update(['expires_at' => now()->subDay()]);

        $this->getJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/badge")->assertForbidden();
    }

    public function test_kiosk_lists_hide_the_attendee_list_but_still_find_scanned_attendees(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $attendee = $this->createBadgeAttendee($eventId, $this->createTicketProduct($eventId), 'Ada', 'Lovelace');
        $listShortId = $this->createList($eventId, badgePrinting: true, kiosk: true);

        $this->getJson("/public/check-in-lists/$listShortId/attendees")->assertForbidden();
        $this->getJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}/detail")->assertForbidden();
        $this->getJson("/public/check-in-lists/$listShortId/attendees/{$attendee['public_id']}")
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Ada');
        $this->getJson("/public/check-in-lists/$listShortId")->assertOk()->assertJsonPath('data.kiosk_mode', true);
    }

    public function test_badge_printing_and_kiosk_mode_are_saved_on_check_in_lists(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createBadgeEvent($accountId);
        $headers = $this->headersFor($owner, $accountId);

        $listId = $this->postJson("/events/$eventId/check-in-lists", [
            'name' => 'Registration desk',
            'badge_printing_enabled' => true,
            'kiosk_mode' => true,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.badge_printing_enabled', true)
            ->assertJsonPath('data.kiosk_mode', true)
            ->json('data.id');

        $this->putJson("/events/$eventId/check-in-lists/$listId", ['name' => 'Front door'], $headers)
            ->assertOk()
            ->assertJsonPath('data.badge_printing_enabled', true)
            ->assertJsonPath('data.kiosk_mode', true);

        $this->putJson("/events/$eventId/check-in-lists/$listId", [
            'name' => 'Registration desk',
            'badge_printing_enabled' => true,
            'kiosk_mode' => false,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.kiosk_mode', false);
    }

    private function createList(int $eventId, bool $badgePrinting = false, bool $kiosk = false): string
    {
        $shortId = 'cil_'.uniqid();
        DB::table('check_in_lists')->insert([
            'short_id' => $shortId,
            'name' => 'Door',
            'event_id' => $eventId,
            'badge_printing_enabled' => $badgePrinting,
            'kiosk_mode' => $kiosk,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $shortId;
    }
}
