<?php

namespace Tests\Feature\Http\Actions\SelfService;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mail\TicketLookup\TicketLookupEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class TicketLookupGuestTicketsTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const BUYER_EMAIL = 'lookup-buyer@example.test';

    private const GUEST_EMAIL = 'lookup-guest@example.test';

    private int $productId;

    private int $priceId;

    private int $occurrenceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);
    }

    public function test_a_named_guest_sees_only_their_own_ticket_from_someone_elses_order(): void
    {
        $orderId = $this->insertOrderFor(self::BUYER_EMAIL);
        $guestShortId = $this->insertTicket($orderId, self::GUEST_EMAIL, 'Grace');
        $this->insertTicket($orderId, self::BUYER_EMAIL, 'Ada');
        $this->insertTicket($orderId, 'third@example.test', 'Katherine');

        $tickets = $this->getJson('/public/ticket-lookup/'.$this->lookupTokenFor(self::GUEST_EMAIL).'/tickets')->assertOk();
        $orders = $this->getJson('/public/ticket-lookup/'.$this->lookupTokenFor(self::GUEST_EMAIL))->assertOk();

        $tickets->assertJsonCount(1, 'data');
        $tickets->assertJsonPath('data.0.short_id', $guestShortId);
        $tickets->assertJsonPath('data.0.first_name', 'Grace');
        $tickets->assertJsonPath('data.0.event_id', $this->eventId);
        $orders->assertJsonCount(0, 'data');
        $this->assertStringNotContainsString('Katherine', $tickets->getContent());
        $this->assertStringNotContainsString(self::BUYER_EMAIL, $tickets->getContent());
    }

    public function test_a_buyer_sees_their_order_and_no_separate_guest_tickets(): void
    {
        $orderId = $this->insertOrderFor(self::BUYER_EMAIL);
        $this->insertTicket($orderId, self::BUYER_EMAIL, 'Ada');
        $token = $this->lookupTokenFor(self::BUYER_EMAIL);

        $this->getJson("/public/ticket-lookup/$token")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/public/ticket-lookup/$token/tickets")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_ticket_accepted_by_the_orders_own_address_is_listed_once_as_a_ticket(): void
    {
        $orderId = $this->insertOrderFor(self::BUYER_EMAIL);
        $acceptedShortId = $this->insertTicket($orderId, self::BUYER_EMAIL, 'Ada');
        DB::table('attendees')->where('short_id', $acceptedShortId)->update(['transferred_at' => now()]);
        $token = $this->lookupTokenFor(self::BUYER_EMAIL);

        $tickets = $this->getJson("/public/ticket-lookup/$token/tickets")->assertOk();
        $orders = $this->getJson("/public/ticket-lookup/$token")->assertOk();

        $tickets->assertJsonCount(1, 'data');
        $tickets->assertJsonPath('data.0.short_id', $acceptedShortId);
        $orders->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString($acceptedShortId, $orders->getContent());
    }

    public function test_cancelled_tickets_and_tickets_on_unfinished_orders_are_not_listed(): void
    {
        $this->insertTicket($this->insertOrderFor(self::BUYER_EMAIL), self::GUEST_EMAIL, 'Cancelled', AttendeeStatus::CANCELLED->name);
        $this->insertTicket($this->insertOrderFor(self::BUYER_EMAIL, OrderStatus::RESERVED->name), self::GUEST_EMAIL, 'Reserved');

        $this->getJson('/public/ticket-lookup/'.$this->lookupTokenFor(self::GUEST_EMAIL).'/tickets')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_expired_or_unknown_lookup_link_lists_no_tickets(): void
    {
        DB::table('ticket_lookup_tokens')->insert([
            'email' => self::GUEST_EMAIL, 'token' => 'tl_old', 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/public/ticket-lookup/tl_old/tickets')->assertStatus(ResponseCodes::HTTP_BAD_REQUEST);
        $this->getJson('/public/ticket-lookup/tl_missing/tickets')->assertStatus(ResponseCodes::HTTP_BAD_REQUEST);
    }

    public function test_a_lookup_email_is_sent_to_a_guest_and_not_to_an_unknown_address(): void
    {
        $this->insertTicket($this->insertOrderFor(self::BUYER_EMAIL), self::GUEST_EMAIL, 'Grace');
        Mail::fake();

        $this->postJson('/public/ticket-lookup', ['email' => self::GUEST_EMAIL])->assertSuccessful();
        $this->postJson('/public/ticket-lookup', ['email' => 'nobody@example.test'])->assertSuccessful();

        Mail::assertQueued(TicketLookupEmail::class, fn ($mail) => $mail->hasTo(self::GUEST_EMAIL));
        Mail::assertNotQueued(TicketLookupEmail::class, fn ($mail) => $mail->hasTo('nobody@example.test'));
    }

    private function insertOrderFor(string $email, string $status = 'COMPLETED'): int
    {
        $orderId = $this->insertOrder($status);
        DB::table('orders')->where('id', $orderId)->update(['email' => $email, 'first_name' => 'Ada', 'last_name' => 'Buyer']);

        return $orderId;
    }

    private function insertTicket(int $orderId, string $email, string $firstName, string $status = 'ACTIVE'): string
    {
        $shortId = 'a_'.uniqid();
        DB::table('attendees')->insert([
            'short_id' => $shortId,
            'public_id' => 'A-'.strtoupper(substr(uniqid(), -7)),
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => 'Guest',
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'event_id' => $this->eventId,
            'status' => $status,
            'locale' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $shortId;
    }

    private function lookupTokenFor(string $email): string
    {
        $token = 'tl_'.uniqid();
        DB::table('ticket_lookup_tokens')->insert([
            'email' => $email, 'token' => $token, 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $token;
    }
}
