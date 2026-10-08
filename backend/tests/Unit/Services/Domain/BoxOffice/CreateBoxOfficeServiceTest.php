<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\BoxOfficeDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeProductAssociationService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\CreateBoxOfficeService;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateBoxOfficeServiceTest extends TestCase
{
    private MockInterface|BoxOfficeRepositoryInterface $boxOfficeRepository;

    private MockInterface|EventProductValidationService $productValidation;

    private MockInterface|BoxOfficeProductAssociationService $productAssociation;

    private MockInterface|BoxOfficePinService $pinService;

    private MockInterface|EventRepositoryInterface $eventRepository;

    private CreateBoxOfficeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->boxOfficeRepository = Mockery::mock(BoxOfficeRepositoryInterface::class);
        $this->productValidation = Mockery::mock(EventProductValidationService::class);
        $this->productAssociation = Mockery::mock(BoxOfficeProductAssociationService::class);
        $this->pinService = Mockery::mock(BoxOfficePinService::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->service = new CreateBoxOfficeService(
            boxOfficeRepository: $this->boxOfficeRepository,
            eventProductValidationService: $this->productValidation,
            productAssociationService: $this->productAssociation,
            pinService: $this->pinService,
            databaseManager: $databaseManager,
            eventRepository: $this->eventRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_box_office_with_hashed_pin_and_products(): void
    {
        $event = (new EventDomainObject)->setId(7)->setTimezone('UTC');
        $this->eventRepository->shouldReceive('findById')->with(7)->andReturn($event);
        $this->productValidation->shouldReceive('validateProductIds')->once()->with([1, 2], 7);
        $this->pinService->shouldReceive('hash')->once()->with('1234')->andReturn('hashed');

        $created = (new BoxOfficeDomainObject)->setId(99);
        $this->boxOfficeRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $attributes) {
                return $attributes[BoxOfficeDomainObjectAbstract::NAME] === 'West gate'
                    && $attributes[BoxOfficeDomainObjectAbstract::PIN_HASH] === 'hashed'
                    && $attributes[BoxOfficeDomainObjectAbstract::EVENT_ID] === 7
                    && $attributes[BoxOfficeDomainObjectAbstract::ALLOW_PRICE_OVERRIDE] === true
                    && str_starts_with($attributes[BoxOfficeDomainObjectAbstract::SHORT_ID], 'bo_');
            })
            ->andReturn($created);

        $this->productAssociation
            ->shouldReceive('addBoxOfficeToProducts')
            ->once()
            ->with(99, [1, 2], false);

        $result = $this->service->createBoxOffice(
            boxOffice: (new BoxOfficeDomainObject)
                ->setName('West gate')
                ->setEventId(7)
                ->setAllowPriceOverride(true),
            productIds: [1, 2],
            pin: '1234',
        );

        $this->assertSame($created, $result);
    }

    public function test_keeps_an_existing_pin_hash_when_no_pin_given(): void
    {
        $event = (new EventDomainObject)->setId(7)->setTimezone('UTC');
        $this->eventRepository->shouldReceive('findById')->andReturn($event);
        $this->productValidation->shouldReceive('validateProductIds')->once();
        $this->pinService->shouldNotReceive('hash');

        $this->boxOfficeRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $attributes) => $attributes[BoxOfficeDomainObjectAbstract::PIN_HASH] === 'cloned-hash'
                && $attributes[BoxOfficeDomainObjectAbstract::CHECK_IN_LIST_ID] === 12)
            ->andReturn((new BoxOfficeDomainObject)->setId(6));
        $this->productAssociation->shouldReceive('addBoxOfficeToProducts')->once();

        $result = $this->service->createBoxOffice(
            boxOffice: (new BoxOfficeDomainObject)
                ->setName('Door')
                ->setEventId(7)
                ->setCheckInListId(12)
                ->setPinHash('cloned-hash'),
            productIds: [],
            pin: null,
        );

        $this->assertSame(6, $result->getId());
    }

    public function test_stores_null_pin_hash_when_no_pin_given(): void
    {
        $event = (new EventDomainObject)->setId(7)->setTimezone('UTC');
        $this->eventRepository->shouldReceive('findById')->andReturn($event);
        $this->productValidation->shouldReceive('validateProductIds')->once();
        $this->pinService->shouldNotReceive('hash');

        $this->boxOfficeRepository
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $attributes) => $attributes[BoxOfficeDomainObjectAbstract::PIN_HASH] === null)
            ->andReturn((new BoxOfficeDomainObject)->setId(5));
        $this->productAssociation->shouldReceive('addBoxOfficeToProducts')->once();

        $result = $this->service->createBoxOffice(
            boxOffice: (new BoxOfficeDomainObject)->setName('Door')->setEventId(7),
            productIds: [],
            pin: null,
        );

        $this->assertSame(5, $result->getId());
    }
}
