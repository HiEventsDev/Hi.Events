<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Attendees;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class PartialEditAttendeeActionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $authToken;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $attendeeId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Mail::fake();

        $this->insertAccountAndOrganizer();
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->occurrenceId = $this->insertOccurrence(capacity: 1, usedCapacity: 1);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null, ProductQuantityAppliesTo::EVENT->name, quantitySold: 1);
        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'products_sold' => 1,
            'attendees_registered' => 1,
            'orders_created' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->sellTickets($this->productId, $this->priceId, $this->occurrenceId, 1);

        $orderId = $this->sellTickets($this->productId, $this->priceId, $this->occurrenceId, 1, attendeeStatus: AttendeeStatus::CANCELLED->name);
        $this->attendeeId = (int) DB::table('attendees')->where('order_id', $orderId)->value('id');
    }

    public function test_activating_a_cancelled_attendee_on_a_full_event_returns_a_validation_error_against_status(): void
    {
        $this->activate()
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', __('This event is sold out'));

        $this->assertDatabaseHas('attendees', ['id' => $this->attendeeId, 'status' => AttendeeStatus::CANCELLED->name]);
        $this->assertSame(1, (int) DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('used_capacity'));
        $this->assertSame(1, (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold'));
    }

    public function test_activating_a_cancelled_attendee_once_the_event_has_room_again(): void
    {
        DB::table('event_occurrences')->where('id', $this->occurrenceId)->update(['capacity' => 2]);

        $this->activate()
            ->assertOk()
            ->assertJsonPath('data.status', AttendeeStatus::ACTIVE->name);

        $this->assertSame(2, (int) DB::table('event_occurrences')->where('id', $this->occurrenceId)->value('used_capacity'));
        $this->assertSame(2, (int) DB::table('product_prices')->where('id', $this->priceId)->value('quantity_sold'));
        $this->assertSame(2, (int) DB::table('event_statistics')->where('event_id', $this->eventId)->value('attendees_registered'));
    }

    private function activate(): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->authToken)
            ->patchJson(sprintf('/events/%d/attendees/%d', $this->eventId, $this->attendeeId), [
                'status' => AttendeeStatus::ACTIVE->name,
            ]);
    }
}
