<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Product;

use HiEvents\Constants;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\ProductFilterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class AvailableProductQuantitiesFetchServiceTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private AvailableProductQuantitiesFetchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.homepage_product_quantities_cache_ttl' => null]);
        $this->service = $this->app->make(AvailableProductQuantitiesFetchService::class);
        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
    }

    public function test_per_date_tier_cap_comes_from_initial_quantity_for_each_occurrence(): void
    {
        $day1 = $this->insertOccurrence(daysAhead: 1);
        $day2 = $this->insertOccurrence(daysAhead: 2);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10, quantitySold: 7);
        $this->sellTickets($productId, $priceId, $day1, 7);

        $this->assertSame(3, $this->availableFor($priceId, $day1));
        $this->assertSame(10, $this->availableFor($priceId, $day2));
    }

    public function test_per_date_override_replaces_the_tier_cap(): void
    {
        $day1 = $this->insertOccurrence(daysAhead: 1);
        $day2 = $this->insertOccurrence(daysAhead: 2);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10);
        DB::table('product_price_occurrence_overrides')->insert([
            'event_occurrence_id' => $day1,
            'product_price_id' => $priceId,
            'price' => null,
            'quantity_available' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(2, $this->availableFor($priceId, $day1));
        $this->assertSame(10, $this->availableFor($priceId, $day2));
    }

    public function test_ticket_sales_count_active_and_offline_pending_attendees_and_reservations(): void
    {
        $day1 = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10);

        $this->sellTickets($productId, $priceId, $day1, 2);
        $this->sellTickets($productId, $priceId, $day1, 1, OrderStatus::AWAITING_OFFLINE_PAYMENT->name, AttendeeStatus::AWAITING_PAYMENT->name);
        $this->sellTickets($productId, $priceId, $day1, 4, OrderStatus::CANCELLED->name, AttendeeStatus::CANCELLED->name);
        $this->reserve($productId, $priceId, $day1, 3);
        $this->reserve($productId, $priceId, $day1, 5, reservedUntil: now()->subMinute());

        $row = $this->row($priceId, $day1);
        $this->assertSame(4, $row->quantity_available);
        $this->assertSame(3, $row->quantity_reserved);
    }

    public function test_partially_cancelled_order_only_counts_remaining_attendees(): void
    {
        $day1 = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 5);

        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->insertOrderItem($orderId, $productId, $priceId, $day1, 3);
        $this->insertAttendee($orderId, $productId, $priceId, $day1);
        $this->insertAttendee($orderId, $productId, $priceId, $day1, AttendeeStatus::CANCELLED->name);
        $this->insertAttendee($orderId, $productId, $priceId, $day1, deleted: true);

        $this->assertSame(4, $this->availableFor($priceId, $day1));
    }

    public function test_event_wide_tier_is_not_clamped_by_the_occurrence(): void
    {
        $day1 = $this->insertOccurrence();
        $productId = $this->insertProduct(ProductType::GENERAL->name);
        $priceId = $this->insertPrice($productId, initialQuantity: 30, appliesTo: ProductQuantityAppliesTo::EVENT->name, quantitySold: 12);

        $this->assertSame(18, $this->availableFor($priceId, $day1));
        $this->assertSame(18, $this->availableFor($priceId, null));
    }

    public function test_per_date_general_product_counts_sold_from_order_items(): void
    {
        $day1 = $this->insertOccurrence(daysAhead: 1);
        $day2 = $this->insertOccurrence(daysAhead: 2);
        $productId = $this->insertProduct(ProductType::GENERAL->name);
        $priceId = $this->insertPrice($productId, initialQuantity: 6);

        $this->insertOrderItem($this->insertOrder(OrderStatus::COMPLETED->name), $productId, $priceId, $day1, 2, ProductType::GENERAL->name);
        $this->insertOrderItem($this->insertOrder(OrderStatus::AWAITING_OFFLINE_PAYMENT->name), $productId, $priceId, $day1, 1, ProductType::GENERAL->name);
        $this->insertOrderItem($this->insertOrder(OrderStatus::CANCELLED->name), $productId, $priceId, $day1, 3, ProductType::GENERAL->name);
        $this->reserve($productId, $priceId, $day1, 1, ProductType::GENERAL->name);

        $this->assertSame(2, $this->availableFor($priceId, $day1));
        $this->assertSame(6, $this->availableFor($priceId, $day2));
    }

    public function test_tiers_of_one_product_can_mix_per_date_and_event_wide(): void
    {
        $day1 = $this->insertOccurrence();
        $productId = $this->insertProduct(priceType: 'TIERED');
        $earlyBird = $this->insertPrice($productId, initialQuantity: 10, label: 'Early bird');
        $general = $this->insertPrice($productId, initialQuantity: 200, appliesTo: ProductQuantityAppliesTo::EVENT->name, quantitySold: 50, label: 'General');
        $this->sellTickets($productId, $earlyBird, $day1, 4);

        $this->assertSame(6, $this->availableFor($earlyBird, $day1));
        $this->assertSame(150, $this->availableFor($general, $day1));
    }

    public function test_occurrence_capacity_still_caps_per_date_tickets(): void
    {
        $day1 = $this->insertOccurrence(capacity: 5, usedCapacity: 3);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10);

        $this->assertSame(2, $this->availableFor($priceId, $day1));
    }

    public function test_occurrence_limits_can_be_skipped_while_per_date_quantities_still_apply(): void
    {
        $day1 = $this->insertOccurrence(capacity: 1, usedCapacity: 1);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 3);
        $this->sellTickets($productId, $priceId, $day1, 1);

        $this->assertSame(0, $this->availableFor($priceId, $day1));

        $withoutLimits = $this->service
            ->getAvailableProductQuantities($this->eventId, ignoreCache: true, eventOccurrenceId: $day1, applyOccurrenceLimits: false)
            ->getAvailableQuantityForPrice($priceId);
        $this->assertSame(2, $withoutLimits);
    }

    public function test_unlimited_per_date_tier_stays_unlimited(): void
    {
        $day1 = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: null);
        $this->sellTickets($productId, $priceId, $day1, 3);

        $this->assertSame(Constants::INFINITE, $this->availableFor($priceId, $day1));
    }

    public function test_event_wide_fetch_does_not_gate_per_date_tiers_on_the_global_total(): void
    {
        $productId = $this->insertProduct();
        $perDate = $this->insertPrice($productId, initialQuantity: 10, quantitySold: 10);
        $eventWide = $this->insertPrice($productId, initialQuantity: 10, appliesTo: ProductQuantityAppliesTo::EVENT->name, quantitySold: 10);

        $this->assertSame(Constants::INFINITE, $this->availableFor($perDate, null));
        $this->assertSame(0, $this->availableFor($eventWide, null));
    }

    public function test_event_wide_availability_subtracts_reservations_but_not_deleted_items(): void
    {
        $day1 = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10, appliesTo: ProductQuantityAppliesTo::EVENT->name, quantitySold: 2);
        $this->reserve($productId, $priceId, $day1, 3);
        $deleted = $this->reserve($productId, $priceId, $day1, 4);
        DB::table('order_items')->where('order_id', $deleted)->update(['deleted_at' => now()]);

        $row = $this->row($priceId, null);
        $this->assertSame(5, $row->quantity_available);
        $this->assertSame(3, $row->quantity_reserved);
    }

    public function test_single_event_ignores_quantity_applies_to(): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $day1 = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10, quantitySold: 4);

        $this->assertSame(6, $this->availableFor($priceId, $day1));
        $this->assertSame(6, $this->availableFor($priceId, null));
    }

    public function test_a_seated_tier_is_capped_by_its_own_quantity_below_the_free_seats(): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $occurrenceId = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 3, appliesTo: ProductQuantityAppliesTo::EVENT->name);
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$productId]]);

        $row = $this->row($priceId, $occurrenceId);

        $this->assertSame(3, $row->quantity_available);
        $this->assertSame($this->premiumTierCapacity(), $row->seats_available);
    }

    public function test_a_seated_tier_larger_than_the_free_seats_is_capped_by_the_seats(): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $occurrenceId = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10000, appliesTo: ProductQuantityAppliesTo::EVENT->name);
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$productId]]);

        $this->assertSame($this->premiumTierCapacity(), $this->availableFor($priceId, $occurrenceId));
    }

    public function test_a_per_date_seated_tier_is_capped_on_each_date(): void
    {
        $day1 = $this->insertOccurrence(daysAhead: 1);
        $day2 = $this->insertOccurrence(daysAhead: 2);
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 2);
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$productId]]);
        $this->sellTickets($productId, $priceId, $day1, 1);

        $this->assertSame(1, $this->availableFor($priceId, $day1));
        $this->assertSame(2, $this->availableFor($priceId, $day2));
    }

    public function test_a_seated_product_total_is_its_free_seats_not_the_sum_of_its_tiers(): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $occurrenceId = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $this->insertPrice($productId, initialQuantity: 10000, appliesTo: ProductQuantityAppliesTo::EVENT->name, label: 'Adult');
        $this->insertPrice($productId, initialQuantity: 10000, appliesTo: ProductQuantityAppliesTo::EVENT->name, label: 'Child');
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$productId]]);

        $product = $this->app->make(ProductFilterService::class)->filterProducts(
            $this->app->make(ProductRepositoryInterface::class)
                ->loadRelation(ProductPriceDomainObject::class)
                ->findWhere([ProductDomainObjectAbstract::ID => $productId]),
            hideSoldOutProducts: false,
            eventOccurrenceId: $occurrenceId,
        )->first();

        $this->assertSame($this->premiumTierCapacity(), $product->getQuantityAvailable());
    }

    public function test_a_capacity_assignment_still_caps_a_seated_product(): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $occurrenceId = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: null);
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$productId]]);
        $this->insertCapacityAssignment($productId, capacity: 2, usedCapacity: 0);

        $this->assertSame(2, $this->availableFor($priceId, $occurrenceId));
    }

    public function test_an_exhausted_capacity_assignment_closes_a_seated_product(): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $occurrenceId = $this->insertOccurrence();
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: null);
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$productId]]);
        $this->insertCapacityAssignment($productId, capacity: 5, usedCapacity: 5);

        $this->assertSame(0, $this->availableFor($priceId, $occurrenceId));
    }

    public function test_an_unseated_product_is_untouched_by_a_seat_map_on_the_same_event(): void
    {
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $occurrenceId = $this->insertOccurrence();
        $seatedProductId = $this->insertProduct();
        $unseatedProductId = $this->insertProduct();
        $unseatedPriceId = $this->insertPrice($unseatedProductId, initialQuantity: 7, quantitySold: 2);
        $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$seatedProductId]]);

        $this->assertSame(5, $this->availableFor($unseatedPriceId, $occurrenceId));
    }

    private function premiumTierCapacity(): int
    {
        $capacity = 0;

        foreach ($this->seatMapFixture('club')['areas'] as $area) {
            foreach ($area['elements'] as $element) {
                foreach ($element['seats'] ?? [] as $seat) {
                    $capacity += $seat['band'] === 'b_premium' ? 1 : 0;
                }

                if (($element['type'] ?? null) === 'zone' && $element['band'] === 'b_premium') {
                    $capacity += $element['capacity'];
                }
            }
        }

        return $capacity;
    }

    private function insertCapacityAssignment(int $productId, ?int $capacity, int $usedCapacity): void
    {
        $capacityAssignmentId = DB::table('capacity_assignments')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Shared pool',
            'capacity' => $capacity,
            'used_capacity' => $usedCapacity,
            'applies_to' => 'PRODUCTS',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_capacity_assignments')->insert([
            'product_id' => $productId,
            'capacity_assignment_id' => $capacityAssignmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function row(int $priceId, ?int $occurrenceId): AvailableProductQuantitiesDTO
    {
        return $this->service
            ->getAvailableProductQuantities($this->eventId, ignoreCache: true, eventOccurrenceId: $occurrenceId)
            ->productQuantities
            ->first(fn (AvailableProductQuantitiesDTO $dto) => $dto->price_id === $priceId);
    }

    private function availableFor(int $priceId, ?int $occurrenceId): int
    {
        return $this->row($priceId, $occurrenceId)->quantity_available;
    }
}
