<?php

namespace Tests\Feature\Http\Actions\CheckInLists;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class CreateAttendeeCheckInPublicActionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_an_attendee_is_checked_in_on_their_own_events_list(): void
    {
        $eventId = $this->createEventInNewAccount();
        $listShortId = $this->createCheckInList($eventId);
        $publicId = $this->createAttendee($eventId, 'Grace', 'Hopper');

        $this->checkIn($listShortId, [$publicId])->assertOk();

        $this->assertDatabaseHas('attendee_check_ins', ['event_id' => $eventId]);
    }

    public function test_another_accounts_attendee_is_treated_as_an_unknown_code(): void
    {
        $listShortId = $this->createCheckInList($this->createEventInNewAccount());
        $otherEventId = $this->createEventInNewAccount();
        $publicId = $this->createAttendee($otherEventId, 'Margaret', 'Hamilton');

        $response = $this->checkIn($listShortId, [$publicId])->assertStatus(409);

        $this->assertStringNotContainsString('Margaret', $response->getContent());
        $this->assertStringNotContainsString('Hamilton', $response->getContent());
        $this->assertDatabaseMissing('attendee_check_ins', ['event_id' => $otherEventId]);
    }

    public function test_unknown_codes_do_not_reveal_which_codes_exist_on_other_events(): void
    {
        $listShortId = $this->createCheckInList($this->createEventInNewAccount());
        $foreignPublicId = $this->createAttendee($this->createEventInNewAccount(), 'Katherine', 'Johnson');
        $missingPublicId = 'A-'.strtoupper(substr(uniqid(), -7));

        $message = $this->checkIn($listShortId, [$foreignPublicId, $missingPublicId])
            ->assertStatus(409)
            ->json('message');

        $this->assertStringContainsString($foreignPublicId, $message);
        $this->assertStringContainsString($missingPublicId, $message);
    }

    public function test_an_unpaid_card_checkout_cannot_check_in_when_offline_orders_may(): void
    {
        $eventId = $this->createEventInNewAccount();
        DB::table('event_settings')->where('event_id', $eventId)->update(['allow_orders_awaiting_offline_payment_to_check_in' => true]);
        $listShortId = $this->createCheckInList($eventId);
        $unpaidCard = $this->createAttendee($eventId, 'Card', 'Abandoned', orderStatus: 'RESERVED', attendeeStatus: 'AWAITING_PAYMENT');
        $offline = $this->createAttendee($eventId, 'Bank', 'Transfer', orderStatus: 'AWAITING_OFFLINE_PAYMENT', attendeeStatus: 'AWAITING_PAYMENT');

        $this->checkIn($listShortId, [$unpaidCard])->assertOk();
        $this->checkIn($listShortId, [$offline])->assertOk();

        $this->assertDatabaseMissing('attendee_check_ins', ['attendee_id' => $this->attendeeId($unpaidCard)]);
        $this->assertDatabaseHas('attendee_check_ins', ['attendee_id' => $this->attendeeId($offline)]);
    }

    public function test_marking_an_unpaid_card_checkout_as_paid_at_the_door_says_it_is_awaiting_payment(): void
    {
        $eventId = $this->createEventInNewAccount();
        DB::table('event_settings')->where('event_id', $eventId)->update(['allow_orders_awaiting_offline_payment_to_check_in' => true]);
        $listShortId = $this->createCheckInList($eventId);
        $unpaidCard = $this->createAttendee($eventId, 'Card', 'Abandoned', orderStatus: 'RESERVED', attendeeStatus: 'AWAITING_PAYMENT');

        $response = $this->postJson("/public/check-in-lists/$listShortId/check-ins", [
            'attendees' => [['public_id' => $unpaidCard, 'action' => 'check-in-and-mark-order-as-paid']],
        ])->assertOk();

        $this->assertStringContainsString('awaiting payment', $response->getContent());
        $this->assertDatabaseMissing('attendee_check_ins', ['attendee_id' => $this->attendeeId($unpaidCard)]);
        $this->assertSame('RESERVED', DB::table('orders')->where('id', DB::table('attendees')->where('public_id', $unpaidCard)->value('order_id'))->value('status'));
    }

    public function test_the_public_list_search_only_matches_email_when_order_details_are_shown(): void
    {
        $eventId = $this->createEventInNewAccount();
        $listShortId = $this->createCheckInList($eventId);
        $this->createAttendee($eventId, 'Ada', 'Lovelace', email: 'hidden-inbox@example.test');

        $this->getJson("/public/check-in-lists/$listShortId/attendees?query=hidden-inbox")
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/public/check-in-lists/$listShortId/attendees?query=Lovelace")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        DB::table('check_in_lists')->where('short_id', $listShortId)->update(['public_show_order_details' => true]);

        $this->getJson("/public/check-in-lists/$listShortId/attendees?query=hidden-inbox")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_an_expired_list_cannot_remove_check_ins(): void
    {
        $eventId = $this->createEventInNewAccount();
        $listShortId = $this->createCheckInList($eventId);
        $publicId = $this->createAttendee($eventId, 'Grace', 'Hopper');
        $checkInShortId = $this->checkIn($listShortId, [$publicId])->assertOk()->json('data.0.short_id');

        DB::table('check_in_lists')->where('short_id', $listShortId)->update(['expires_at' => now()->subHour()]);

        $this->deleteJson("/public/check-in-lists/$listShortId/check-ins/$checkInShortId")->assertStatus(409);

        $this->assertDatabaseHas('attendee_check_ins', ['short_id' => $checkInShortId, 'deleted_at' => null]);
    }

    private function attendeeId(string $publicId): int
    {
        return DB::table('attendees')->where('public_id', $publicId)->value('id');
    }

    private function checkIn(string $listShortId, array $publicIds)
    {
        return $this->postJson("/public/check-in-lists/$listShortId/check-ins", [
            'attendees' => array_map(fn (string $publicId) => ['public_id' => $publicId, 'action' => 'check-in'], $publicIds),
        ]);
    }

    private function createEventInNewAccount(): int
    {
        [, $accountId] = $this->createAccountOwner();

        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));
        DB::table('event_settings')->insert(['event_id' => $eventId, 'created_at' => now(), 'updated_at' => now()]);

        return $eventId;
    }

    private function createCheckInList(int $eventId): string
    {
        $shortId = 'cil_'.uniqid();
        DB::table('check_in_lists')->insert([
            'short_id' => $shortId,
            'name' => 'Door',
            'event_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $shortId;
    }

    private function createAttendee(
        int $eventId,
        string $firstName,
        string $lastName,
        string $email = 'attendee@example.test',
        string $orderStatus = 'COMPLETED',
        string $attendeeStatus = 'ACTIVE',
    ): string {
        $productId = DB::table('products')->insertGetId([
            'title' => 'General Admission',
            'event_id' => $eventId,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $priceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $eventId,
            'currency' => 'USD',
            'status' => $orderStatus,
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => 'buyer@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $publicId = 'A-'.strtoupper(substr(uniqid(), -7));
        DB::table('attendees')->insert([
            'short_id' => 'att_'.uniqid(),
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'event_id' => $eventId,
            'public_id' => $publicId,
            'status' => $attendeeStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }
}
