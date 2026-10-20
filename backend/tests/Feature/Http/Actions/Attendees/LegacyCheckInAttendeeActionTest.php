<?php

namespace Tests\Feature\Http\Actions\Attendees;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class LegacyCheckInAttendeeActionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const OPERATION_PATH = '/events/{eventId}/attendees/{attendeePublicId}/check_in';

    private User $owner;

    private int $accountId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->eventId = $this->createEvent($this->accountId, $this->createOrganizer($this->accountId));
    }

    public function test_checking_out_an_attendee_who_was_never_checked_in_is_a_conflict(): void
    {
        $publicId = $this->createAttendee();

        $this->checkIn($publicId, 'check_out')
            ->assertStatus(409)
            ->assertJsonPath('message', 'This attendee is not checked in');
    }

    public function test_an_attendee_can_be_checked_in_and_out_once(): void
    {
        $publicId = $this->createAttendee();

        $this->checkIn($publicId, 'check_in')->assertOk()->assertJsonPath('data.public_id', $publicId);
        $this->assertNotNull(DB::table('attendees')->where('public_id', $publicId)->value('checked_in_at'));

        $this->checkIn($publicId, 'check_in')->assertStatus(409);

        $this->checkIn($publicId, 'check_out')->assertOk();
        $this->assertNull(DB::table('attendees')->where('public_id', $publicId)->value('checked_in_at'));

        $this->checkIn($publicId, 'check_out')->assertStatus(409);
    }

    public function test_an_inactive_attendee_cannot_be_checked_in(): void
    {
        $publicId = $this->createAttendee(AttendeeStatus::CANCELLED->name);

        $this->checkIn($publicId, 'check_in')->assertStatus(409);
    }

    public function test_the_api_reference_marks_the_endpoint_deprecated_and_points_to_check_in_lists(): void
    {
        $operation = json_decode(
            file_get_contents(base_path('resources/api-reference/openapi.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['paths'][self::OPERATION_PATH]['post'];

        $this->assertTrue($operation['deprecated'] ?? false);
        $this->assertStringContainsString('GET /events/{eventId}/check-in-lists', $operation['description']);
        $this->assertStringContainsString('POST /public/check-in-lists/{checkInListShortId}/check-ins', $operation['description']);
    }

    private function checkIn(string $attendeePublicId, string $action)
    {
        return $this->postJson(
            "/events/{$this->eventId}/attendees/$attendeePublicId/check_in",
            ['action' => $action],
            $this->headersFor($this->owner, $this->accountId),
        );
    }

    private function createAttendee(string $status = AttendeeStatus::ACTIVE->name): string
    {
        $productId = DB::table('products')->insertGetId([
            'title' => 'General Admission',
            'event_id' => $this->eventId,
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
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $publicId = 'ATT_'.uniqid();
        DB::table('attendees')->insert([
            'short_id' => 'att_'.uniqid(),
            'email' => 'attendee@example.test',
            'first_name' => 'Ada',
            'last_name' => 'Attendee',
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'event_id' => $this->eventId,
            'public_id' => $publicId,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $publicId;
    }
}
