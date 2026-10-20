<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\ProductTerminology;
use Tests\TestCase;

class ProductTerminologyTest extends TestCase
{
    public function test_for_category_maps_categories_to_terminology(): void
    {
        $this->assertSame(ProductTerminology::CLASSES, ProductTerminology::forCategory('WELLNESS'));
        $this->assertSame(ProductTerminology::CLASSES, ProductTerminology::forCategory('SPIRITUALITY'));
        $this->assertSame(ProductTerminology::CLASSES, ProductTerminology::forCategory('DANCE'));
        $this->assertSame(ProductTerminology::REGISTRATIONS, ProductTerminology::forCategory('WORKSHOP'));
        $this->assertSame(ProductTerminology::REGISTRATIONS, ProductTerminology::forCategory('EDUCATION'));
        $this->assertSame(ProductTerminology::BOOKINGS, ProductTerminology::forCategory('TOURS'));
        $this->assertSame(ProductTerminology::PASSES, ProductTerminology::forCategory('BUSINESS'));
        $this->assertSame(ProductTerminology::PASSES, ProductTerminology::forCategory('TECH'));
        $this->assertSame(ProductTerminology::TICKETS, ProductTerminology::forCategory('MUSIC'));
        $this->assertSame(ProductTerminology::TICKETS, ProductTerminology::forCategory('OTHER'));
    }

    public function test_for_category_falls_back_to_tickets_for_null_and_unknown_values(): void
    {
        $this->assertSame(ProductTerminology::TICKETS, ProductTerminology::forCategory(null));
        $this->assertSame(ProductTerminology::TICKETS, ProductTerminology::forCategory('NOT_A_CATEGORY'));
    }

    public function test_every_event_category_resolves_to_a_terminology(): void
    {
        foreach (EventCategory::cases() as $category) {
            $terminology = $category->terminology();

            $this->assertNotSame('', $terminology->defaultProductCategoryName());
            $this->assertNotSame('', $terminology->defaultNoProductsMessage());
            $this->assertNotSame('', $terminology->defaultContinueButtonText());
        }
    }

    public function test_tickets_terminology_keeps_existing_defaults(): void
    {
        $this->assertSame('Tickets', ProductTerminology::TICKETS->defaultProductCategoryName());
        $this->assertSame('There are no tickets available for this event', ProductTerminology::TICKETS->defaultNoProductsMessage());
        $this->assertSame('Continue', ProductTerminology::TICKETS->defaultContinueButtonText());
        $this->assertNull(ProductTerminology::TICKETS->defaultGetTicketsButtonText());
    }

    public function test_non_ticket_terminologies_provide_get_tickets_button_text(): void
    {
        $this->assertSame('Book Now', ProductTerminology::CLASSES->defaultGetTicketsButtonText());
        $this->assertSame('Book Now', ProductTerminology::BOOKINGS->defaultGetTicketsButtonText());
        $this->assertSame('Register', ProductTerminology::REGISTRATIONS->defaultGetTicketsButtonText());
        $this->assertSame('Get Passes', ProductTerminology::PASSES->defaultGetTicketsButtonText());
    }
}
