<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use Illuminate\Support\Facades\DB;

trait InsertsRecurringEventRows
{
    private int $accountId;

    private int $userId;

    private int $organizerId;

    private int $eventId;

    private function insertAccountAndOrganizer(): void
    {
        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Recurring Organizer',
            'email' => 'org+'.uniqid().'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEvent(string $type = EventType::RECURRING->name): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Recurring Event '.uniqid(),
            'type' => $type,
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOccurrence(?int $capacity = null, int $usedCapacity = 0, int $daysAhead = 1): int
    {
        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $this->eventId,
            'start_date' => now()->addDays($daysAhead)->toDateTimeString(),
            'end_date' => now()->addDays($daysAhead)->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'capacity' => $capacity,
            'used_capacity' => $usedCapacity,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProduct(string $productType = ProductType::TICKET->name, string $priceType = 'PAID'): int
    {
        return DB::table('products')->insertGetId([
            'title' => ucfirst(strtolower($productType)).' '.uniqid(),
            'event_id' => $this->eventId,
            'product_type' => $productType,
            'type' => $priceType,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPrice(
        int $productId,
        ?int $initialQuantity,
        string $appliesTo = ProductQuantityAppliesTo::OCCURRENCE->name,
        int $quantitySold = 0,
        ?string $label = null,
    ): int {
        return DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 10.00,
            'label' => $label,
            'initial_quantity_available' => $initialQuantity,
            'quantity_applies_to' => $appliesTo,
            'quantity_sold' => $quantitySold,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOrder(string $status, ?\DateTimeInterface $reservedUntil = null): int
    {
        return DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => $status,
            'reserved_until' => $reservedUntil,
            'public_id' => 'PUB_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOrderItem(int $orderId, int $productId, int $priceId, int $occurrenceId, int $quantity, string $productType = ProductType::TICKET->name): void
    {
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'product_type' => $productType,
            'event_occurrence_id' => $occurrenceId,
            'quantity' => $quantity,
            'price' => 10.00,
            'total_before_additions' => $quantity * 10.00,
        ]);
    }

    private function insertAttendee(int $orderId, int $productId, int $priceId, int $occurrenceId, string $status = AttendeeStatus::ACTIVE->name, bool $deleted = false): void
    {
        DB::table('attendees')->insert([
            'short_id' => 'att_'.uniqid(),
            'email' => 'attendee+'.uniqid().'@example.test',
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'event_id' => $this->eventId,
            'event_occurrence_id' => $occurrenceId,
            'public_id' => 'ATT_'.uniqid(),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);
    }

    private function sellTickets(int $productId, int $priceId, int $occurrenceId, int $count, string $orderStatus = OrderStatus::COMPLETED->name, string $attendeeStatus = AttendeeStatus::ACTIVE->name): int
    {
        $orderId = $this->insertOrder($orderStatus);
        $this->insertOrderItem($orderId, $productId, $priceId, $occurrenceId, $count);
        for ($i = 0; $i < $count; $i++) {
            $this->insertAttendee($orderId, $productId, $priceId, $occurrenceId, $attendeeStatus);
        }

        return $orderId;
    }

    private function reserve(int $productId, int $priceId, int $occurrenceId, int $quantity, string $productType = ProductType::TICKET->name, ?\DateTimeInterface $reservedUntil = null): int
    {
        $orderId = $this->insertOrder(OrderStatus::RESERVED->name, $reservedUntil ?? now()->addMinutes(15));
        $this->insertOrderItem($orderId, $productId, $priceId, $occurrenceId, $quantity, $productType);

        return $orderId;
    }
}
