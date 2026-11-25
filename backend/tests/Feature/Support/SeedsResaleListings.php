<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\Models\AccountConfiguration;
use Illuminate\Support\Facades\DB;

trait SeedsResaleListings
{
    use InsertsRecurringEventRows;

    private const SELLER_EMAIL = 'seller@example.test';

    private const STRIPE_ACCOUNT = 'acct_resale';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $sellerOrderId;

    private function seedResaleEvent(int $ticketsSold = 4): void
    {
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        DB::table('organizer_stripe_platforms')->insert([
            'organizer_id' => $this->organizerId,
            'stripe_account_id' => self::STRIPE_ACCOUNT,
            'stripe_setup_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedLiveEventWithSoldTickets($ticketsSold);
    }

    private function seedLiveEventWithSoldTickets(int $ticketsSold, bool $allowResale = true, int $daysAhead = 30): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'allow_ticket_resale' => $allowResale,
            'ticket_resale_cutoff_minutes' => 120,
            'payment_providers' => json_encode([PaymentProviders::STRIPE->name]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->occurrenceId = $this->insertOccurrence(daysAhead: $daysAhead);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice(
            $this->productId,
            $ticketsSold,
            ProductQuantityAppliesTo::EVENT->name,
            quantitySold: $ticketsSold,
            label: 'Early bird',
            price: 50.00,
        );
        $this->sellerOrderId = $this->sellTickets($this->productId, $this->priceId, $this->occurrenceId, $ticketsSold);

        DB::table('orders')->where('id', $this->sellerOrderId)->update([
            'email' => self::SELLER_EMAIL,
            'first_name' => 'Sam',
            'last_name' => 'Seller',
            'locale' => 'en',
            'total_gross' => 50 * $ticketsSold,
            'total_before_additions' => 50 * $ticketsSold,
            'payment_provider' => PaymentProviders::STRIPE->name,
            'payment_status' => OrderPaymentStatus::PAYMENT_RECEIVED->name,
        ]);
        DB::table('order_items')->where('order_id', $this->sellerOrderId)->update([
            'price' => 50,
            'total_before_additions' => 50 * $ticketsSold,
            'total_gross' => 50 * $ticketsSold,
        ]);
    }

    private function insertListing(int $attendeeOffset = 0, array $attributes = []): int
    {
        return DB::table('ticket_resale_listings')->insertGetId(array_merge([
            'event_id' => $this->eventId,
            'order_id' => $this->sellerOrderId,
            'attendee_id' => $this->sellerAttendeeId($attendeeOffset),
            'product_price_id' => $this->priceId,
            'event_occurrence_id' => $this->occurrenceId,
            'status' => TicketResaleListingStatus::LISTED->name,
            'min_unit_price' => 50.00,
            'estimated_refund' => 47.50,
            'connected_account_id' => self::STRIPE_ACCOUNT,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function insertListingOnAnotherEvent(bool $allowResale = true, int $daysAhead = 30): int
    {
        $current = [$this->eventId, $this->occurrenceId, $this->productId, $this->priceId, $this->sellerOrderId];

        $this->seedLiveEventWithSoldTickets(1, $allowResale, $daysAhead);
        $listingId = $this->insertListing();

        [$this->eventId, $this->occurrenceId, $this->productId, $this->priceId, $this->sellerOrderId] = $current;

        return $listingId;
    }

    private function sellerAttendeeId(int $offset = 0): int
    {
        return DB::table('attendees')->where('order_id', $this->sellerOrderId)->orderBy('id')->skip($offset)->value('id');
    }
}
