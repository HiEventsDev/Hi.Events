<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Eloquent\AttendeeRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class AttendeeRepositoryTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private AttendeeRepository $repository;

    private int $day1;

    private int $day2;

    private int $productId;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(AttendeeRepository::class);
        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        $this->day1 = $this->insertOccurrence(daysAhead: 1);
        $this->day2 = $this->insertOccurrence(daysAhead: 2);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, initialQuantity: 10);
    }

    public function test_sold_quantities_per_occurrence_are_grouped_by_price(): void
    {
        $otherPriceId = $this->insertPrice($this->productId, initialQuantity: 10);
        $this->sellTickets($this->productId, $this->priceId, $this->day1, 2);
        $this->sellTickets($this->productId, $otherPriceId, $this->day1, 1);
        $this->sellTickets($this->productId, $this->priceId, $this->day2, 5);

        $this->assertSame(
            [$this->priceId => 2, $otherPriceId => 1],
            $this->repository->getSoldQuantitiesByPriceForOccurrence($this->day1),
        );
    }

    public function test_sold_quantities_count_offline_pending_but_not_cancelled_or_deleted(): void
    {
        $this->sellTickets($this->productId, $this->priceId, $this->day1, 1, OrderStatus::AWAITING_OFFLINE_PAYMENT->name, AttendeeStatus::AWAITING_PAYMENT->name);
        $this->sellTickets($this->productId, $this->priceId, $this->day1, 1, OrderStatus::RESERVED->name, AttendeeStatus::AWAITING_PAYMENT->name);
        $this->sellTickets($this->productId, $this->priceId, $this->day1, 1, OrderStatus::CANCELLED->name, AttendeeStatus::CANCELLED->name);
        $orderId = $this->sellTickets($this->productId, $this->priceId, $this->day1, 1);
        $this->insertAttendee($orderId, $this->productId, $this->priceId, $this->day1, deleted: true);

        $this->assertSame([$this->priceId => 2], $this->repository->getSoldQuantitiesByPriceForOccurrence($this->day1));
    }

    public function test_max_sold_per_occurrence_returns_the_busiest_date_for_each_price(): void
    {
        $this->sellTickets($this->productId, $this->priceId, $this->day1, 2);
        $this->sellTickets($this->productId, $this->priceId, $this->day2, 6);

        $this->assertSame([$this->priceId => 6], $this->repository->getMaxSoldPerOccurrenceByPrice([$this->priceId]));
        $this->assertSame([], $this->repository->getMaxSoldPerOccurrenceByPrice([$this->priceId + 1000]));
    }
}
