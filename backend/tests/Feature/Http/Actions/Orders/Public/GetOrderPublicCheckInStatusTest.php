<?php

namespace Tests\Feature\Http\Actions\Orders\Public;

use HiEvents\DomainObjects\Status\OrderStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class GetOrderPublicCheckInStatusTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $productId;

    private int $attendeeId;

    private string $orderShortId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $this->productId = $this->insertProduct();
        $priceId = $this->insertPrice($this->productId, null);

        $orderId = $this->sellTickets($this->productId, $priceId, $occurrenceId, 1, OrderStatus::COMPLETED->name);
        $this->orderShortId = DB::table('orders')->where('id', $orderId)->value('short_id');
        $this->attendeeId = DB::table('attendees')->where('order_id', $orderId)->value('id');
    }

    public function test_the_public_order_says_whether_each_attendee_is_checked_in(): void
    {
        $this->getJson("/public/events/{$this->eventId}/order/{$this->orderShortId}")
            ->assertOk()
            ->assertJsonPath('data.attendees.0.is_checked_in', false);

        $this->checkIn($this->attendeeId);

        $this->getJson("/public/events/{$this->eventId}/order/{$this->orderShortId}")
            ->assertOk()
            ->assertJsonPath('data.attendees.0.is_checked_in', true);
    }

    private function checkIn(int $attendeeId): void
    {
        $checkInListId = DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cil_'.uniqid(),
            'name' => 'Door',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendee_check_ins')->insert([
            'short_id' => 'aci_'.uniqid(),
            'check_in_list_id' => $checkInListId,
            'product_id' => $this->productId,
            'attendee_id' => $attendeeId,
            'event_id' => $this->eventId,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
