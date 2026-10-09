<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\ProductPurchaseStatus;
use HiEvents\Http\DTO\FilterFieldDTO;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\User;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseDTO;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseFilterDTO;
use HiEvents\Repository\Eloquent\OrderItemRepository;
use HiEvents\Repository\Eloquent\OrderRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductPurchasesRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OrderItemRepository $repository;

    private int $userId;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    private int $ticketId;

    private int $ticketPriceId;

    private int $vipPriceId;

    private int $otherTicketId;

    private int $otherTicketPriceId;

    private int $merchId;

    private int $merchPriceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(OrderItemRepository::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->eventId = $this->insertEvent();

        [$this->ticketId, $this->ticketPriceId] = $this->insertProduct($this->eventId, 'General Admission', ProductType::TICKET);
        $this->vipPriceId = $this->insertPrice($this->ticketId, 'VIP');
        [$this->otherTicketId, $this->otherTicketPriceId] = $this->insertProduct($this->eventId, 'Student', ProductType::TICKET);
        [$this->merchId, $this->merchPriceId] = $this->insertProduct($this->eventId, 'T-Shirt', ProductType::GENERAL);
    }

    public function test_completed_ticket_order_counts_active_attendees_as_sold(): void
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED, email: 'ada@example.test', firstName: 'Ada', lastName: 'Lovelace');
        $this->insertTicketLine($orderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE, AttendeeStatus::ACTIVE], 50.00);

        $purchase = $this->onlyPurchase($this->ticketId);

        $this->assertSame(ProductPurchaseStatus::SOLD->name, $purchase->status);
        $this->assertSame(2, $purchase->soldQuantity);
        $this->assertSame(0, $purchase->cancelledQuantity);
        $this->assertSame(50.0, $purchase->lineTotal);
        $this->assertSame('Ada', $purchase->firstName);
        $this->assertSame('ada@example.test', $purchase->email);
        $this->assertSame('General Admission', $purchase->productTitle);
    }

    public function test_individually_cancelled_attendees_are_not_counted_as_sold(): void
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertTicketLine($orderId, $this->ticketId, $this->ticketPriceId, [
            AttendeeStatus::ACTIVE,
            AttendeeStatus::ACTIVE,
            AttendeeStatus::CANCELLED,
        ], 75.00);

        $purchase = $this->onlyPurchase($this->ticketId);

        $this->assertSame(ProductPurchaseStatus::SOLD->name, $purchase->status);
        $this->assertSame(2, $purchase->soldQuantity);
        $this->assertSame(1, $purchase->cancelledQuantity);
    }

    public function test_completed_order_with_every_attendee_cancelled_is_cancelled(): void
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertTicketLine($orderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::CANCELLED], 25.00);

        $purchase = $this->onlyPurchase($this->ticketId);

        $this->assertSame(ProductPurchaseStatus::CANCELLED->name, $purchase->status);
        $this->assertSame(0, $purchase->soldQuantity);
        $this->assertSame(1, $purchase->cancelledQuantity);
    }

    public function test_cancelled_orders_are_cancelled_for_tickets_and_products(): void
    {
        $orderId = $this->insertOrder(OrderStatus::CANCELLED);
        $this->insertTicketLine($orderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::CANCELLED, AttendeeStatus::CANCELLED], 50.00);
        $this->insertItem($orderId, $this->merchId, $this->merchPriceId, ProductType::GENERAL, 3, 60.00);

        $ticketPurchase = $this->onlyPurchase($this->ticketId);
        $merchPurchase = $this->onlyPurchase($this->merchId);

        $this->assertSame(ProductPurchaseStatus::CANCELLED->name, $ticketPurchase->status);
        $this->assertSame(2, $ticketPurchase->cancelledQuantity);
        $this->assertSame(ProductPurchaseStatus::CANCELLED->name, $merchPurchase->status);
        $this->assertSame(0, $merchPurchase->soldQuantity);
        $this->assertSame(3, $merchPurchase->cancelledQuantity);
    }

    public function test_offline_payment_orders_are_awaiting_payment_not_sold(): void
    {
        $orderId = $this->insertOrder(OrderStatus::AWAITING_OFFLINE_PAYMENT);
        $this->insertTicketLine($orderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::AWAITING_PAYMENT, AttendeeStatus::CANCELLED], 50.00);
        $this->insertItem($orderId, $this->merchId, $this->merchPriceId, ProductType::GENERAL, 2, 40.00);

        $ticketPurchase = $this->onlyPurchase($this->ticketId);
        $merchPurchase = $this->onlyPurchase($this->merchId);

        $this->assertSame(ProductPurchaseStatus::AWAITING_PAYMENT->name, $ticketPurchase->status);
        $this->assertSame(0, $ticketPurchase->soldQuantity);
        $this->assertSame(1, $ticketPurchase->awaitingPaymentQuantity);
        $this->assertSame(1, $ticketPurchase->cancelledQuantity);
        $this->assertSame(ProductPurchaseStatus::AWAITING_PAYMENT->name, $merchPurchase->status);
        $this->assertSame(2, $merchPurchase->awaitingPaymentQuantity);
    }

    public function test_reserved_abandoned_and_deleted_orders_are_never_purchases(): void
    {
        $reservedOrderId = $this->insertOrder(OrderStatus::RESERVED);
        $this->insertTicketLine($reservedOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::AWAITING_PAYMENT], 25.00);
        $this->insertItem($reservedOrderId, $this->merchId, $this->merchPriceId, ProductType::GENERAL, 1, 20.00);

        $abandonedOrderId = $this->insertOrder(OrderStatus::ABANDONED);
        $this->insertTicketLine($abandonedOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::AWAITING_PAYMENT], 25.00);

        $deletedOrderId = $this->insertOrder(OrderStatus::COMPLETED, deleted: true);
        $this->insertTicketLine($deletedOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE], 25.00);

        $this->assertCount(0, $this->purchases($this->ticketId));
        $this->assertCount(0, $this->purchases($this->merchId));
        $this->assertSame(0, $this->summary($this->ticketId)->soldQuantity);
    }

    public function test_attendee_moved_to_another_ticket_is_listed_under_the_new_ticket(): void
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertItem($orderId, $this->ticketId, $this->ticketPriceId, ProductType::TICKET, 2, 50.00);
        $this->insertAttendee($orderId, $this->ticketId, $this->ticketPriceId, AttendeeStatus::ACTIVE);
        $this->insertAttendee($orderId, $this->otherTicketId, $this->otherTicketPriceId, AttendeeStatus::ACTIVE);

        $originalPurchase = $this->onlyPurchase($this->ticketId);
        $movedPurchase = $this->onlyPurchase($this->otherTicketId);

        $this->assertSame(1, $originalPurchase->soldQuantity);
        $this->assertSame(1, $movedPurchase->soldQuantity);
        $this->assertNull($movedPurchase->lineTotal);
    }

    public function test_tiers_are_separate_lines_and_split_items_are_combined(): void
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertItem($orderId, $this->ticketId, $this->ticketPriceId, ProductType::TICKET, 2, 19.99);
        $this->insertItem($orderId, $this->ticketId, $this->ticketPriceId, ProductType::TICKET, 1, 10.00);
        $this->insertAttendees($orderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE, AttendeeStatus::ACTIVE, AttendeeStatus::ACTIVE]);
        $this->insertTicketLine($orderId, $this->ticketId, $this->vipPriceId, [AttendeeStatus::ACTIVE], 100.00);

        $purchases = $this->purchases($this->ticketId);

        $this->assertCount(2, $purchases);
        $general = $purchases->first(fn (ProductPurchaseDTO $purchase) => $purchase->productPriceId === $this->ticketPriceId);
        $vip = $purchases->first(fn (ProductPurchaseDTO $purchase) => $purchase->productPriceId === $this->vipPriceId);
        $this->assertSame(3, $general->soldQuantity);
        $this->assertSame(29.99, $general->lineTotal);
        $this->assertSame(1, $vip->soldQuantity);
        $this->assertSame('VIP', $vip->priceLabel);
        $this->assertSame(100.0, $vip->lineTotal);
    }

    public function test_summary_separates_sold_awaiting_cancelled_and_refunds(): void
    {
        $paidOrderId = $this->insertOrder(OrderStatus::COMPLETED, email: 'Ada@Example.test');
        $this->insertTicketLine($paidOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE, AttendeeStatus::CANCELLED], 50.00);

        $repeatBuyerOrderId = $this->insertOrder(OrderStatus::COMPLETED, email: 'ada@example.test', refundStatus: OrderRefundStatus::PARTIALLY_REFUNDED);
        $this->insertTicketLine($repeatBuyerOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE], 25.00);

        $walkInOrderId = $this->insertOrder(OrderStatus::COMPLETED, email: null);
        $this->insertTicketLine($walkInOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE], 25.00);

        $offlineOrderId = $this->insertOrder(OrderStatus::AWAITING_OFFLINE_PAYMENT, email: 'grace@example.test');
        $this->insertTicketLine($offlineOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::AWAITING_PAYMENT], 25.00);

        $refundedCancelledOrderId = $this->insertOrder(OrderStatus::CANCELLED, email: 'linus@example.test', refundStatus: OrderRefundStatus::REFUNDED);
        $this->insertTicketLine($refundedCancelledOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::CANCELLED], 25.00);

        $summary = $this->summary($this->ticketId);

        $this->assertSame(3, $summary->soldQuantity);
        $this->assertSame(1, $summary->awaitingPaymentQuantity);
        $this->assertSame(2, $summary->cancelledQuantity);
        $this->assertSame(3, $summary->buyerCount);
        $this->assertSame(100.0, $summary->grossSales);
        $this->assertSame(1, $summary->refundedOrderCount);
    }

    public function test_purchases_are_scoped_to_the_event_product_and_occurrence(): void
    {
        $occurrenceId = $this->insertOccurrence($this->eventId);
        $otherOccurrenceId = $this->insertOccurrence($this->eventId);

        $orderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertTicketLine($orderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE], 25.00, $occurrenceId);
        $this->insertTicketLine($orderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE, AttendeeStatus::ACTIVE], 50.00, $otherOccurrenceId);
        $this->insertTicketLine($orderId, $this->otherTicketId, $this->otherTicketPriceId, [AttendeeStatus::ACTIVE], 25.00, $occurrenceId);

        $otherEventId = $this->insertEvent();
        [$otherEventTicketId, $otherEventPriceId] = $this->insertProduct($otherEventId, 'Elsewhere', ProductType::TICKET);
        $otherEventOrderId = $this->insertOrder(OrderStatus::COMPLETED, eventId: $otherEventId);
        $this->insertTicketLine($otherEventOrderId, $otherEventTicketId, $otherEventPriceId, [AttendeeStatus::ACTIVE], 25.00);

        $this->assertCount(2, $this->purchases($this->ticketId));
        $this->assertCount(3, $this->repository->getAllProductPurchases(new ProductPurchaseFilterDTO(eventId: $this->eventId)));

        $occurrencePurchases = $this->repository->getAllProductPurchases(new ProductPurchaseFilterDTO(
            eventId: $this->eventId,
            productId: $this->ticketId,
            eventOccurrenceId: $otherOccurrenceId,
        ));
        $this->assertCount(1, $occurrencePurchases);
        $this->assertSame(2, $occurrencePurchases->first()->soldQuantity);
        $this->assertSame(50.0, $occurrencePurchases->first()->lineTotal);
        $this->assertNotNull($occurrencePurchases->first()->occurrenceStartDate);

        $this->assertSame(2, $this->summary($this->ticketId, $otherOccurrenceId)->soldQuantity);
        $this->assertCount(0, $this->repository->getAllProductPurchases(new ProductPurchaseFilterDTO(
            eventId: $this->eventId,
            productId: $otherEventTicketId,
        )));
    }

    public function test_status_refund_and_search_filters_and_pagination(): void
    {
        $soldOrderId = $this->insertOrder(OrderStatus::COMPLETED, email: 'ada@example.test', firstName: 'Ada', lastName: 'Lovelace');
        $this->insertTicketLine($soldOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE], 25.00);

        $refundedOrderId = $this->insertOrder(OrderStatus::COMPLETED, email: 'grace@example.test', firstName: 'Grace', lastName: 'Hopper', refundStatus: OrderRefundStatus::REFUNDED);
        $this->insertTicketLine($refundedOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE], 25.00);

        $cancelledOrderId = $this->insertOrder(OrderStatus::CANCELLED, email: 'linus@example.test');
        $this->insertTicketLine($cancelledOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::CANCELLED], 25.00);

        $active = $this->purchases($this->ticketId, statuses: [ProductPurchaseStatus::SOLD->name, ProductPurchaseStatus::AWAITING_PAYMENT->name]);
        $this->assertEqualsCanonicalizing([$soldOrderId, $refundedOrderId], $active->pluck('orderId')->all());

        $refunded = $this->purchases($this->ticketId, refundStatuses: [OrderRefundStatus::REFUNDED->name]);
        $this->assertSame([$refundedOrderId], $refunded->pluck('orderId')->all());

        $this->assertSame([$soldOrderId], $this->purchases($this->ticketId, query: 'ada love')->pluck('orderId')->all());
        $this->assertSame([$refundedOrderId], $this->purchases($this->ticketId, query: 'GRACE@')->pluck('orderId')->all());

        $page = $this->repository->findProductPurchases(
            new ProductPurchaseFilterDTO(eventId: $this->eventId, productId: $this->ticketId),
            page: 2,
            perPage: 2,
        );
        $this->assertSame(3, $page->total());
        $this->assertCount(1, $page->items());
    }

    public function test_order_list_can_be_filtered_by_product_including_moved_attendees(): void
    {
        $ticketOrderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertTicketLine($ticketOrderId, $this->ticketId, $this->ticketPriceId, [AttendeeStatus::ACTIVE], 25.00);

        $merchOrderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertItem($merchOrderId, $this->merchId, $this->merchPriceId, ProductType::GENERAL, 1, 20.00);

        $movedOrderId = $this->insertOrder(OrderStatus::COMPLETED);
        $this->insertItem($movedOrderId, $this->otherTicketId, $this->otherTicketPriceId, ProductType::TICKET, 1, 25.00);
        $this->insertAttendee($movedOrderId, $this->ticketId, $this->ticketPriceId, AttendeeStatus::ACTIVE);

        $orders = $this->app->make(OrderRepository::class)->findByEventId($this->eventId, new QueryParamsDTO(
            filter_fields: collect([new FilterFieldDTO(field: 'product_id', operator: 'in', value: (string) $this->ticketId)]),
        ));

        $this->assertEqualsCanonicalizing([$ticketOrderId, $movedOrderId], collect($orders->items())->map->getId()->all());
    }

    private function onlyPurchase(int $productId): ProductPurchaseDTO
    {
        $purchases = $this->purchases($productId);
        $this->assertCount(1, $purchases);

        return $purchases->first();
    }

    private function purchases(int $productId, array $statuses = [], array $refundStatuses = [], ?string $query = null)
    {
        return $this->repository->getAllProductPurchases(new ProductPurchaseFilterDTO(
            eventId: $this->eventId,
            productId: $productId,
            statuses: $statuses,
            refundStatuses: $refundStatuses,
            query: $query,
        ));
    }

    private function summary(int $productId, ?int $occurrenceId = null)
    {
        return $this->repository->getProductPurchaseSummary(new ProductPurchaseFilterDTO(
            eventId: $this->eventId,
            productId: $productId,
            eventOccurrenceId: $occurrenceId,
        ));
    }

    private function insertEvent(): int
    {
        return DB::table('events')->insertGetId([
            'title' => 'Test Event',
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid('', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOccurrence(int $eventId): int
    {
        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid('', true),
            'event_id' => $eventId,
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date' => now()->addDay()->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function insertProduct(int $eventId, string $title, ProductType $type): array
    {
        $productId = DB::table('products')->insertGetId([
            'title' => $title,
            'event_id' => $eventId,
            'product_type' => $type->name,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$productId, $this->insertPrice($productId, null)];
    }

    private function insertPrice(int $productId, ?string $label): int
    {
        return DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 25.00,
            'label' => $label,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOrder(
        OrderStatus $status,
        ?string $email = 'buyer@example.test',
        ?string $firstName = 'Test',
        ?string $lastName = 'Buyer',
        ?OrderRefundStatus $refundStatus = null,
        bool $deleted = false,
        ?int $eventId = null,
    ): int {
        return DB::table('orders')->insertGetId([
            'short_id' => 'o_'.uniqid(),
            'public_id' => 'O-'.strtoupper(uniqid()),
            'event_id' => $eventId ?? $this->eventId,
            'currency' => 'USD',
            'status' => $status->name,
            'refund_status' => $refundStatus?->name,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'reserved_until' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);
    }

    private function insertItem(int $orderId, int $productId, int $priceId, ProductType $type, int $quantity, float $total, ?int $occurrenceId = null): void
    {
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'product_type' => $type->name,
            'event_occurrence_id' => $occurrenceId,
            'quantity' => $quantity,
            'price' => round($total / $quantity, 2),
            'total_before_additions' => $total,
            'total_gross' => $total,
        ]);
    }

    /**
     * @param  AttendeeStatus[]  $attendeeStatuses
     */
    private function insertTicketLine(int $orderId, int $productId, int $priceId, array $attendeeStatuses, float $total, ?int $occurrenceId = null): void
    {
        $this->insertItem($orderId, $productId, $priceId, ProductType::TICKET, count($attendeeStatuses), $total, $occurrenceId);
        $this->insertAttendees($orderId, $productId, $priceId, $attendeeStatuses, $occurrenceId);
    }

    /**
     * @param  AttendeeStatus[]  $statuses
     */
    private function insertAttendees(int $orderId, int $productId, int $priceId, array $statuses, ?int $occurrenceId = null): void
    {
        foreach ($statuses as $status) {
            $this->insertAttendee($orderId, $productId, $priceId, $status, $occurrenceId);
        }
    }

    private function insertAttendee(int $orderId, int $productId, int $priceId, AttendeeStatus $status, ?int $occurrenceId = null): void
    {
        DB::table('attendees')->insert([
            'short_id' => 'a_'.uniqid().random_int(10, 99),
            'public_id' => 'A-'.strtoupper(uniqid()).random_int(10, 99),
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'event_id' => DB::table('orders')->where('id', $orderId)->value('event_id'),
            'event_occurrence_id' => $occurrenceId,
            'status' => $status->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
