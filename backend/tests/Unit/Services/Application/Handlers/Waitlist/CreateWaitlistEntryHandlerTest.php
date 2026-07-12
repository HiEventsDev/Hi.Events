<?php

namespace Tests\Unit\Services\Application\Handlers\Waitlist;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Waitlist\CreateWaitlistEntryHandler;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\CreateWaitlistEntryDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Waitlist\CreateWaitlistEntryService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateWaitlistEntryHandlerTest extends TestCase
{
    private CreateWaitlistEntryService|MockInterface $createWaitlistEntryService;

    private EventSettingsRepositoryInterface|MockInterface $eventSettingsRepository;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private ProductPriceRepositoryInterface|MockInterface $productPriceRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private EventOccurrenceRepositoryInterface|MockInterface $occurrenceRepository;

    private OccurrencePurchaseEligibilityService|MockInterface $occurrenceEligibilityService;

    private CreateWaitlistEntryHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createWaitlistEntryService = Mockery::mock(CreateWaitlistEntryService::class);
        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->productPriceRepository = Mockery::mock(ProductPriceRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->occurrenceRepository = Mockery::mock(EventOccurrenceRepositoryInterface::class);
        $this->occurrenceEligibilityService = Mockery::mock(OccurrencePurchaseEligibilityService::class);

        $this->handler = new CreateWaitlistEntryHandler(
            $this->createWaitlistEntryService,
            $this->eventSettingsRepository,
            $this->eventRepository,
            $this->productPriceRepository,
            $this->productRepository,
            $this->occurrenceRepository,
            $this->occurrenceEligibilityService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_single_event_without_occurrence_id_resolves_to_first_occurrence(): void
    {
        $dto = new CreateWaitlistEntryDTO(
            event_id: 10,
            product_price_id: 20,
            email: 'buyer@example.com',
            first_name: 'Buyer',
        );

        $event = new EventDomainObject;
        $event->setType('SINGLE');
        $this->eventRepository->shouldReceive('findById')->with(10)->andReturn($event);

        $firstOccurrence = (new EventOccurrenceDomainObject)
            ->setId(42)
            ->setStatus(EventOccurrenceStatus::ACTIVE->name)
            ->setStartDate(now()->addDay()->toDateTimeString())
            ->setEndDate(now()->addDays(2)->toDateTimeString());

        $this->occurrenceRepository
            ->shouldReceive('findWhere')
            ->andReturn(collect([$firstOccurrence]));

        $eventSettings = new EventSettingDomainObject;
        $eventSettings->setWaitlistEnabled(true);
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($eventSettings);

        $productPrice = (new ProductPriceDomainObject)->setId(20)->setProductId(30);
        $this->productPriceRepository->shouldReceive('findById')->with(20)->andReturn($productPrice);

        $product = (new ProductDomainObject)->setId(30);
        $this->productRepository->shouldReceive('findFirstWhere')->andReturn($product);

        $this->occurrenceRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 42, 'event_id' => 10])
            ->andReturn($firstOccurrence);

        $this->occurrenceEligibilityService
            ->shouldReceive('assertProductsVisibleOnOccurrence')
            ->once()
            ->with(42, [30]);

        // Regression: the DTO rebuild previously fataled via a non-existent fromArray().
        $this->createWaitlistEntryService
            ->shouldReceive('createEntry')
            ->once()
            ->with(
                Mockery::on(fn (CreateWaitlistEntryDTO $rebuilt) => $rebuilt->event_occurrence_id === 42
                    && $rebuilt->event_id === 10
                    && $rebuilt->product_price_id === 20
                    && $rebuilt->email === 'buyer@example.com'),
                $eventSettings,
                $product,
            )
            ->andReturn(new WaitlistEntryDomainObject);

        $result = $this->handler->handle($dto);

        $this->assertInstanceOf(WaitlistEntryDomainObject::class, $result);
    }
}
