<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\BoxOfficeDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeProductAssociationService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\UpdateBoxOfficeService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateBoxOfficeServiceTest extends TestCase
{
    private MockInterface|BoxOfficeRepositoryInterface $boxOfficeRepository;

    private MockInterface|BoxOfficeProductAssociationService $productAssociation;

    private UpdateBoxOfficeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->boxOfficeRepository = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $this->productAssociation = Mockery::mock(BoxOfficeProductAssociationService::class);

        $productValidation = Mockery::mock(EventProductValidationService::class);
        $productValidation->shouldReceive('validateProductIds');

        $eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $eventRepository->shouldReceive('findById')->andReturn((new EventDomainObject)->setId(7)->setTimezone('UTC'));

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->service = new UpdateBoxOfficeService(
            databaseManager: $databaseManager,
            eventProductValidationService: $productValidation,
            productAssociationService: $this->productAssociation,
            boxOfficeRepository: $this->boxOfficeRepository,
            eventRepository: $eventRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function boxOffice(): BoxOfficeDomainObject
    {
        return (new BoxOfficeDomainObject)->setId(3)->setEventId(7)->setName('Door');
    }

    public function test_throws_when_box_office_not_found(): void
    {
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();

        $this->expectException(ResourceNotFoundException::class);

        $this->service->updateBoxOffice($this->boxOffice(), []);
    }

    public function test_updates_attributes_and_product_scope(): void
    {
        $this->boxOfficeRepository->shouldReceive('findFirstWhere')->twice()->andReturn($this->boxOffice());
        $this->boxOfficeRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->withArgs(fn (array $attributes) => $attributes[BoxOfficeDomainObjectAbstract::NAME] === 'Door'
                && ! array_key_exists(BoxOfficeDomainObjectAbstract::PIN_HASH, $attributes));
        $this->productAssociation->shouldReceive('addBoxOfficeToProducts')->once()->with(3, [4]);

        $result = $this->service->updateBoxOffice($this->boxOffice(), [4]);

        $this->assertSame(3, $result->getId());
    }
}
