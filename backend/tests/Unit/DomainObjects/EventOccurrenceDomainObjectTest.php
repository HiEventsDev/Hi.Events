<?php

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
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
}
