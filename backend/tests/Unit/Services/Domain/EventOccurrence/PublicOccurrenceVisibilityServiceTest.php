<?php

namespace Tests\Unit\Services\Domain\EventOccurrence;

use Closure;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Services\Domain\EventOccurrence\PublicOccurrenceVisibilityService;
use Tests\TestCase;

class PublicOccurrenceVisibilityServiceTest extends TestCase
{
    private PublicOccurrenceVisibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PublicOccurrenceVisibilityService;
    }

    public function test_should_hide_sold_out_occurrences_for_recurring_event_with_setting_enabled(): void
    {
        $event = (new EventDomainObject)
            ->setType(EventType::RECURRING->name)
            ->setEventSettings((new EventSettingDomainObject)->setHideSoldOutOccurrences(true))
            ->setProductCategories(collect());

        $this->assertTrue($this->service->shouldHideSoldOutOccurrences($event));
    }

    public function test_should_not_hide_sold_out_occurrences_for_single_event(): void
    {
        $event = (new EventDomainObject)
            ->setType(EventType::SINGLE->name)
            ->setEventSettings((new EventSettingDomainObject)->setHideSoldOutOccurrences(true))
            ->setProductCategories(collect());

        $this->assertFalse($this->service->shouldHideSoldOutOccurrences($event));
    }

    public function test_should_not_hide_sold_out_occurrences_when_setting_disabled(): void
    {
        $event = (new EventDomainObject)
            ->setType(EventType::RECURRING->name)
            ->setEventSettings((new EventSettingDomainObject)->setHideSoldOutOccurrences(false))
            ->setProductCategories(collect());

        $this->assertFalse($this->service->shouldHideSoldOutOccurrences($event));
    }

    public function test_should_not_hide_sold_out_occurrences_when_waitlist_product_exists(): void
    {
        $category = new ProductCategoryDomainObject;
        $category->setProducts(collect([
            (new ProductDomainObject)->setWaitlistEnabled(true),
        ]));

        $event = (new EventDomainObject)
            ->setType(EventType::RECURRING->name)
            ->setEventSettings((new EventSettingDomainObject)->setHideSoldOutOccurrences(true))
            ->setProductCategories(collect([$category]));

        $this->assertFalse($this->service->shouldHideSoldOutOccurrences($event));
    }

    public function test_build_where_conditions_for_recurring_event_hiding_sold_out(): void
    {
        $where = $this->service->buildWhereConditions(
            eventId: 5,
            isRecurring: true,
            hideSoldOutOccurrences: true,
        );

        $this->assertSame(5, $where[EventOccurrenceDomainObjectAbstract::EVENT_ID]);
        $this->assertContains(
            [EventOccurrenceDomainObjectAbstract::STATUS, '!=', EventOccurrenceStatus::CANCELLED->name],
            $where,
        );
        $this->assertCount(2, array_filter($where, static fn ($condition) => $condition instanceof Closure));
    }

    public function test_build_where_conditions_for_single_event_without_hiding(): void
    {
        $where = $this->service->buildWhereConditions(
            eventId: 5,
            isRecurring: false,
            hideSoldOutOccurrences: false,
        );

        $this->assertSame(5, $where[EventOccurrenceDomainObjectAbstract::EVENT_ID]);
        $this->assertCount(0, array_filter($where, static fn ($condition) => $condition instanceof Closure));
    }
}
