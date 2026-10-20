<?php

namespace Tests\Unit\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use Tests\TestCase;

class EventOccurrenceDomainObjectTest extends TestCase
{
    private function occurrence(?bool $override): EventOccurrenceDomainObject
    {
        $occurrence = new EventOccurrenceDomainObject;
        $occurrence->setShowAvailableCapacity($override);

        return $occurrence;
    }

    public function test_override_null_inherits_event_default_true(): void
    {
        $this->assertTrue($this->occurrence(null)->shouldShowAvailableCapacity(true));
    }

    public function test_override_null_inherits_event_default_false(): void
    {
        $this->assertFalse($this->occurrence(null)->shouldShowAvailableCapacity(false));
    }

    public function test_override_true_shows_regardless_of_event_default(): void
    {
        $this->assertTrue($this->occurrence(true)->shouldShowAvailableCapacity(false));
        $this->assertTrue($this->occurrence(true)->shouldShowAvailableCapacity(true));
    }

    public function test_override_false_hides_regardless_of_event_default(): void
    {
        $this->assertFalse($this->occurrence(false)->shouldShowAvailableCapacity(true));
        $this->assertFalse($this->occurrence(false)->shouldShowAvailableCapacity(false));
    }

    public function test_available_capacity_is_null_when_capacity_is_null(): void
    {
        $occurrence = new EventOccurrenceDomainObject;
        $occurrence->setCapacity(null);

        $this->assertNull($occurrence->getAvailableCapacity());
    }

    public function test_available_capacity_never_goes_negative(): void
    {
        $occurrence = new EventOccurrenceDomainObject;
        $occurrence->setCapacity(10);
        $occurrence->setUsedCapacity(15);

        $this->assertSame(0, $occurrence->getAvailableCapacity());
    }

    public function test_status_derives_sold_out_when_active_and_full(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(10)
            ->setUsedCapacity(10);

        $this->assertSame(EventOccurrenceStatus::SOLD_OUT->name, $occurrence->getStatus());
        $this->assertTrue($occurrence->isSoldOut());
        $this->assertFalse($occurrence->isActive());
    }

    public function test_status_stays_active_while_capacity_remains(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(10)
            ->setUsedCapacity(9);

        $this->assertSame(EventOccurrenceStatus::ACTIVE->name, $occurrence->getStatus());
        $this->assertFalse($occurrence->isSoldOut());
        $this->assertTrue($occurrence->isActive());
    }

    public function test_status_stays_active_when_capacity_unlimited(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setCapacity(null)
            ->setUsedCapacity(500);

        $this->assertSame(EventOccurrenceStatus::ACTIVE->name, $occurrence->getStatus());
        $this->assertFalse($occurrence->isSoldOut());
    }

    public function test_cancelled_status_is_never_derived_to_sold_out(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)
            ->setStatus(EventOccurrenceStatus::CANCELLED->name)
            ->setCapacity(10)
            ->setUsedCapacity(10);

        $this->assertSame(EventOccurrenceStatus::CANCELLED->name, $occurrence->getStatus());
        $this->assertFalse($occurrence->isSoldOut());
        $this->assertTrue($occurrence->isCancelled());
    }

    public function test_is_past_is_false_while_occurrence_is_running(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)
            ->setStartDate(Carbon::now('UTC')->subDay()->toDateTimeString())
            ->setEndDate(Carbon::now('UTC')->addHour()->toDateTimeString());

        $this->assertFalse($occurrence->isPast());
    }

    public function test_is_past_is_true_once_end_date_passes(): void
    {
        $occurrence = (new EventOccurrenceDomainObject)
            ->setStartDate(Carbon::now('UTC')->subDays(2)->toDateTimeString())
            ->setEndDate(Carbon::now('UTC')->subDay()->toDateTimeString());

        $this->assertTrue($occurrence->isPast());
    }

    public function test_is_past_falls_back_to_start_date_when_end_is_null(): void
    {
        $started = (new EventOccurrenceDomainObject)
            ->setStartDate(Carbon::now('UTC')->subHour()->toDateTimeString());
        $upcoming = (new EventOccurrenceDomainObject)
            ->setStartDate(Carbon::now('UTC')->addHour()->toDateTimeString());

        $this->assertTrue($started->isPast());
        $this->assertFalse($upcoming->isPast());
    }
}
