<?php

namespace Tests\Feature\Http\Actions\Orders;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\Mailer\SentMessage;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class SeatLabelsInOrderEmailsTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const SEAT_LABEL = 'Stalls · A-10';

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, null);

        $this->orderId = $this->insertOrder(OrderStatus::AWAITING_OFFLINE_PAYMENT->name);
        DB::table('orders')->where('id', $this->orderId)->update([
            'email' => 'buyer@example.test',
            'first_name' => 'Robin',
            'last_name' => 'Buyer',
            'total_gross' => 10,
            'total_before_additions' => 10,
            'payment_status' => 'AWAITING_OFFLINE_PAYMENT',
        ]);
        $this->insertOrderItem($this->orderId, $productId, $priceId, $occurrenceId, 1);
        $this->insertAttendee($this->orderId, $productId, $priceId, $occurrenceId, AttendeeStatus::AWAITING_PAYMENT->name);
        DB::table('attendees')->where('order_id', $this->orderId)->update(['seat_label' => self::SEAT_LABEL]);
    }

    public function test_the_email_sent_when_an_order_is_marked_as_paid_lists_its_seats(): void
    {
        $this->postJson("/events/{$this->eventId}/orders/{$this->orderId}/mark-as-paid", [], $this->authHeaders())
            ->assertOk();

        $this->assertSeatsInEmailTo('buyer@example.test');
    }

    public function test_a_resent_order_confirmation_lists_its_seats(): void
    {
        DB::table('orders')->where('id', $this->orderId)->update(['status' => OrderStatus::COMPLETED->name]);
        DB::table('attendees')->where('order_id', $this->orderId)->update(['status' => AttendeeStatus::ACTIVE->name]);

        $this->postJson("/events/{$this->eventId}/orders/{$this->orderId}/resend_confirmation", [], $this->authHeaders())
            ->assertNoContent();

        $this->assertSeatsInEmailTo('buyer@example.test');
    }

    private function assertSeatsInEmailTo(string $address): void
    {
        $bodies = app('mail.manager')->mailer()->getSymfonyTransport()->messages()
            ->filter(fn (SentMessage $message) => collect($message->getOriginalMessage()->getTo())->contains(fn ($to) => $to->getAddress() === $address))
            ->map(fn (SentMessage $message) => $message->getOriginalMessage()->getHtmlBody());

        $this->assertNotEmpty($bodies);
        $this->assertTrue($bodies->contains(fn (?string $html) => str_contains((string) $html, e(self::SEAT_LABEL))));
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId))];
    }
}
