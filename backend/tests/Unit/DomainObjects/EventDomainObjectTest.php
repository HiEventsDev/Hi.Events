<?php

namespace Tests\Unit\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\EventLifecycleStatus;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EventDomainObjectTest extends TestCase
{
    private function createOccurrence(
        string $startDate,
        ?string $endDate = null,
        string $status = 'ACTIVE',
    ): EventOccurrenceDomainObject {
        $occurrence = new EventOccurrenceDomainObject;
        $occurrence->setStartDate($startDate);
        $occurrence->setEndDate($endDate);
        $occurrence->setStatus($status);

        return $occurrence;
    }

    private function createEvent(?Collection $occurrences = null, ?string $timezone = null): EventDomainObject
    {
        $event = new EventDomainObject;

        if ($occurrences !== null) {
            $event->setEventOccurrences($occurrences);
        }

        if ($timezone !== null) {
            $event->setTimezone($timezone);
        }

        return $event;
    }

    public function test_get_start_date_returns_earliest_occurrence_start_date(): void
    {
        $earlier = Carbon::now()->subDays(3)->toDateTimeString();
        $later = Carbon::now()->subDay()->toDateTimeString();

        $occurrences = collect([
            $this->createOccurrence($later),
            $this->createOccurrence($earlier),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals($earlier, $event->getStartDate());
    }

    public function test_get_start_date_returns_null_when_no_occurrences(): void
    {
        $event = $this->createEvent();
        $this->assertNull($event->getStartDate());

        $eventWithEmpty = $this->createEvent(collect([]));
        $this->assertNull($eventWithEmpty->getStartDate());
    }

    public function test_get_end_date_returns_latest_occurrence_end_date(): void
    {
        $earlierEnd = Carbon::now()->addDay()->toDateTimeString();
        $laterEnd = Carbon::now()->addDays(3)->toDateTimeString();

        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDay()->toDateTimeString(),
                $earlierEnd,
            ),
            $this->createOccurrence(
                Carbon::now()->toDateTimeString(),
                $laterEnd,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals($laterEnd, $event->getEndDate());
    }

    public function test_get_end_date_falls_back_to_latest_start_date_when_no_end_dates(): void
    {
        $earlierStart = Carbon::now()->subDay()->toDateTimeString();
        $laterStart = Carbon::now()->addDay()->toDateTimeString();

        $occurrences = collect([
            $this->createOccurrence($earlierStart),
            $this->createOccurrence($laterStart),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals($laterStart, $event->getEndDate());
    }

    public function test_get_end_date_returns_null_when_no_occurrences(): void
    {
        $event = $this->createEvent();
        $this->assertNull($event->getEndDate());

        $eventWithEmpty = $this->createEvent(collect([]));
        $this->assertNull($eventWithEmpty->getEndDate());
    }

    public function test_is_event_in_past_returns_true_when_all_occurrences_are_past(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDays(3)->toDateTimeString(),
                Carbon::now()->subDays(2)->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->subDays(2)->toDateTimeString(),
                Carbon::now()->subDay()->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventInPast());
    }

    public function test_is_event_in_past_returns_false_when_some_occurrences_are_future(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDays(2)->toDateTimeString(),
                Carbon::now()->subDay()->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->addDay()->toDateTimeString(),
                Carbon::now()->addDays(2)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertFalse($event->isEventInPast());
    }

    public function test_is_event_in_future_returns_true_when_earliest_start_is_future(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->addDay()->toDateTimeString(),
                Carbon::now()->addDays(2)->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->addDays(3)->toDateTimeString(),
                Carbon::now()->addDays(4)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventInFuture());
    }

    public function test_is_event_in_future_returns_false_when_earliest_start_is_past(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDay()->toDateTimeString(),
                Carbon::now()->addDay()->toDateTimeString(),
            ),
            $this->createOccurrence(
                Carbon::now()->addDays(2)->toDateTimeString(),
                Carbon::now()->addDays(3)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertFalse($event->isEventInFuture());
    }

    public function test_is_event_ongoing_returns_true_when_active_occurrence_has_started_but_not_ended(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                Carbon::now()->addHour()->toDateTimeString(),
                EventOccurrenceStatus::ACTIVE->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventOngoing());
    }

    public function test_is_event_ongoing_returns_false_for_cancelled_occurrences(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                Carbon::now()->addHour()->toDateTimeString(),
                EventOccurrenceStatus::CANCELLED->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertFalse($event->isEventOngoing());
    }

    public function test_is_event_ongoing_returns_true_when_active_occurrence_has_no_end_date(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                null,
                EventOccurrenceStatus::ACTIVE->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertTrue($event->isEventOngoing());
    }

    public function test_is_event_ongoing_returns_false_when_no_occurrences(): void
    {
        $event = $this->createEvent();
        $this->assertFalse($event->isEventOngoing());

        $eventWithEmpty = $this->createEvent(collect([]));
        $this->assertFalse($eventWithEmpty->isEventOngoing());
    }

    public function test_get_lifecycle_status_returns_ongoing_when_ongoing(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subHour()->toDateTimeString(),
                Carbon::now()->addHour()->toDateTimeString(),
                EventOccurrenceStatus::ACTIVE->name,
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals(EventLifecycleStatus::ONGOING->name, $event->getLifecycleStatus());
    }

    public function test_get_lifecycle_status_returns_upcoming_when_all_future(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->addDay()->toDateTimeString(),
                Carbon::now()->addDays(2)->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals(EventLifecycleStatus::UPCOMING->name, $event->getLifecycleStatus());
    }

    public function test_get_lifecycle_status_returns_ended_when_all_past(): void
    {
        $occurrences = collect([
            $this->createOccurrence(
                Carbon::now()->subDays(3)->toDateTimeString(),
                Carbon::now()->subDay()->toDateTimeString(),
            ),
        ]);

        $event = $this->createEvent($occurrences);

        $this->assertEquals(EventLifecycleStatus::ENDED->name, $event->getLifecycleStatus());
    }

    public function test_get_lifecycle_status_returns_upcoming_when_no_occurrences(): void
    {
        $event = $this->createEvent(collect());

        $this->assertEquals(EventLifecycleStatus::UPCOMING->name, $event->getLifecycleStatus());
    }

    public function test_is_recurring_returns_true_for_recurring_type(): void
    {
        $event = new EventDomainObject;
        $event->setType(EventType::RECURRING->name);

        $this->assertTrue($event->isRecurring());
    }

    public function test_is_recurring_returns_false_for_single_type(): void
    {
        $event = new EventDomainObject;
        $event->setType(EventType::SINGLE->name);

        $this->assertFalse($event->isRecurring());
    }

    private function createProduct(ProductPriceType $type): ProductDomainObject
    {
        return (new ProductDomainObject)->setType($type->name);
    }

    public function test_has_paid_products_returns_false_when_no_products(): void
    {
        $event = new EventDomainObject;

        $this->assertFalse($event->hasPaidProducts());
    }

    public function test_has_paid_products_returns_false_for_only_free_products(): void
    {
        $event = (new EventDomainObject)->setProducts(collect([
            $this->createProduct(ProductPriceType::FREE),
            $this->createProduct(ProductPriceType::REGISTRATION),
        ]));

        $this->assertFalse($event->hasPaidProducts());
    }

    public function test_has_paid_products_returns_true_for_paid_product(): void
    {
        $event = (new EventDomainObject)->setProducts(collect([
            $this->createProduct(ProductPriceType::FREE),
            $this->createProduct(ProductPriceType::PAID),
        ]));

        $this->assertTrue($event->hasPaidProducts());
    }

    public function test_has_paid_products_returns_true_for_donation_product(): void
    {
        $event = (new EventDomainObject)->setProducts(collect([
            $this->createProduct(ProductPriceType::DONATION),
        ]));

        $this->assertTrue($event->hasPaidProducts());
    }

    public function test_has_paid_products_detects_paid_product_in_categories(): void
    {
        $category = new ProductCategoryDomainObject;
        $category->setProducts(collect([
            $this->createProduct(ProductPriceType::FREE),
            $this->createProduct(ProductPriceType::TIERED),
        ]));

        $event = (new EventDomainObject)->setProductCategories(collect([$category]));

        $this->assertTrue($event->hasPaidProducts());
    }
}
