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

    private function createAttendee(int $eventId, string $firstName, string $lastName): string
    {
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
            'status' => 'COMPLETED',
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
            'email' => 'attendee@example.test',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'event_id' => $eventId,
            'public_id' => $publicId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }
}
