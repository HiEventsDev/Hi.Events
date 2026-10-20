<?php

namespace Tests\Unit\Services\Domain\ProductCategory;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Services\Domain\ProductCategory\CreateProductCategoryService;
use Mockery;
use Tests\TestCase;

class CreateProductCategoryServiceTest extends TestCase
{
    private ProductCategoryRepositoryInterface $productCategoryRepository;

    private CreateProductCategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productCategoryRepository = Mockery::mock(ProductCategoryRepositoryInterface::class);
        $this->service = new CreateProductCategoryService($this->productCategoryRepository);
    }

    public function test_default_category_uses_ticket_wording_for_ticket_categories(): void
    {
        $this->assertDefaultCategoryCreated(
            eventCategory: 'MUSIC',
            expectedName: 'Tickets',
            expectedNoProductsMessage: 'There are no tickets available for this event',
        );
    }

    public function test_default_category_uses_class_wording_for_wellness_events(): void
    {
        $this->assertDefaultCategoryCreated(
            eventCategory: 'WELLNESS',
            expectedName: 'Classes',
            expectedNoProductsMessage: 'There are no classes available for this event',
        );
    }

    public function test_default_category_uses_registration_wording_for_workshop_events(): void
    {
        $this->assertDefaultCategoryCreated(
            eventCategory: 'WORKSHOP',
            expectedName: 'Registration',
            expectedNoProductsMessage: 'Registration is not open for this event',
        );
    }

    private function assertDefaultCategoryCreated(
        string $eventCategory,
        string $expectedName,
        string $expectedNoProductsMessage,
    ): void {
        $event = (new EventDomainObject)
            ->setId(100)
            ->setCategory($eventCategory);

        $this->productCategoryRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(
                static fn (array $attributes) => $attributes['event_id'] === 100
                    && $attributes['name'] === $expectedName
                    && $attributes['no_products_message'] === $expectedNoProductsMessage
            ))
            ->andReturn(new ProductCategoryDomainObject);

        $this->service->createDefaultProductCategory($event);
    }
}
