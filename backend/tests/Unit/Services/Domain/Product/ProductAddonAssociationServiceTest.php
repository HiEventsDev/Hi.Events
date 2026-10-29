<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use HiEvents\Services\Domain\Product\Exception\InvalidAddonProductException;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Domain\Product\ProductAddonAssociationService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductAddonAssociationServiceTest extends TestCase
{
    private ProductRepositoryInterface|MockInterface $productRepository;

    private EventProductValidationService|MockInterface $eventProductValidationService;

    private ProductAddonAssociationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->eventProductValidationService = Mockery::mock(EventProductValidationService::class);

        $this->service = new ProductAddonAssociationService(
            $this->productRepository,
            $this->eventProductValidationService,
        );
    }

    public function test_rejects_self_reference(): void
    {
        $this->expectException(InvalidAddonProductException::class);

        $this->service->associateAddons(productId: 1, eventId: 10, addonProductIds: [2, 1]);
    }

    public function test_dedupes_and_syncs_valid_addons(): void
    {
        $this->eventProductValidationService
            ->shouldReceive('validateProductIds')
            ->once()
            ->with([2, 3], 10);

        $this->productRepository
            ->shouldReceive('syncAddons')
            ->once()
            ->with(1, [2, 3]);

        $this->service->associateAddons(productId: 1, eventId: 10, addonProductIds: [2, 3, 2, '3']);
        $this->assertTrue(true);
    }

    public function test_empty_list_syncs_without_validating(): void
    {
        $this->eventProductValidationService->shouldNotReceive('validateProductIds');

        $this->productRepository
            ->shouldReceive('syncAddons')
            ->once()
            ->with(1, []);

        $this->service->associateAddons(productId: 1, eventId: 10, addonProductIds: []);
        $this->assertTrue(true);
    }

    public function test_propagates_cross_event_validation_failure(): void
    {
        $this->eventProductValidationService
            ->shouldReceive('validateProductIds')
            ->andThrow(new UnrecognizedProductIdException('Invalid product ids: 5'));

        $this->productRepository->shouldNotReceive('syncAddons');

        $this->expectException(UnrecognizedProductIdException::class);

        $this->service->associateAddons(productId: 1, eventId: 10, addonProductIds: [5]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
