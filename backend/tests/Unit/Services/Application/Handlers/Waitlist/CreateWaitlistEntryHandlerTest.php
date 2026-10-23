<?php

namespace Tests\Unit\Services\Application\Handlers\Waitlist;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\Waitlist\CreateWaitlistEntryHandler;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\CreateWaitlistEntryDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\PromoCode\PromoCodeUsageValidationService;
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

    private PromoCodeRepositoryInterface|MockInterface $promoCodeRepository;

    private PromoCodeUsageValidationService|MockInterface $promoCodeUsageValidationService;

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
        $this->promoCodeRepository = Mockery::mock(PromoCodeRepositoryInterface::class);
        $this->promoCodeUsageValidationService = Mockery::mock(PromoCodeUsageValidationService::class);

        $this->handler = new CreateWaitlistEntryHandler(
            $this->createWaitlistEntryService,
            $this->eventSettingsRepository,
            $this->eventRepository,
            $this->productPriceRepository,
            $this->productRepository,
            $this->occurrenceRepository,
            $this->occurrenceEligibilityService,
            $this->promoCodeRepository,
            $this->promoCodeUsageValidationService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rejects_waitlist_sign_ups_for_events_that_are_not_live(): void
    {
        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 10])
            ->andReturn((new EventDomainObject)->setType('SINGLE')->setStatus('DRAFT'));

        $this->createWaitlistEntryService->shouldNotReceive('createEntry');
        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(new CreateWaitlistEntryDTO(
            event_id: 10,
            product_price_id: 20,
            email: 'victim@example.com',
            first_name: 'Buyer',
        ));
    }

    public function test_single_event_without_occurrence_id_resolves_to_first_occurrence(): void
    {
        $this->assertWaitlistEntryCreated(promoCode: null, expectedPromoCode: null, rebuiltPromoCode: null);
    }

    public function test_a_usable_promo_code_is_passed_to_the_waitlist(): void
    {
        $promoCode = (new PromoCodeDomainObject)->setId(5);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')
            ->with(['code' => 'members', 'event_id' => 10])
            ->andReturn($promoCode);
        $this->promoCodeUsageValidationService->shouldReceive('isPromoCodeUsable')->with($promoCode)->andReturn(true);

        $this->assertWaitlistEntryCreated(promoCode: ' MEMBERS ', expectedPromoCode: $promoCode, rebuiltPromoCode: ' MEMBERS ');
    }

    public function test_a_promo_code_that_cannot_be_used_is_ignored(): void
    {
        $promoCode = (new PromoCodeDomainObject)->setId(5);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->andReturn($promoCode);
        $this->promoCodeUsageValidationService->shouldReceive('isPromoCodeUsable')->with($promoCode)->andReturn(false);

        $this->assertWaitlistEntryCreated(promoCode: 'MEMBERS', expectedPromoCode: null, rebuiltPromoCode: 'MEMBERS');
    }

    private function assertWaitlistEntryCreated(?string $promoCode, ?PromoCodeDomainObject $expectedPromoCode, ?string $rebuiltPromoCode): void
    {
        $dto = new CreateWaitlistEntryDTO(
            event_id: 10,
            product_price_id: 20,
            email: 'buyer@example.com',
            first_name: 'Buyer',
            promo_code: $promoCode,
        );

        $event = new EventDomainObject;
        $event->setType('SINGLE');
        $event->setStatus('LIVE');
        $this->eventRepository->shouldReceive('findFirstWhere')->with(['id' => 10])->andReturn($event);

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

        $this->createWaitlistEntryService
            ->shouldReceive('createEntry')
            ->once()
            ->with(
                Mockery::on(fn (CreateWaitlistEntryDTO $rebuilt) => $rebuilt->event_occurrence_id === 42
                    && $rebuilt->event_id === 10
                    && $rebuilt->product_price_id === 20
                    && $rebuilt->email === 'buyer@example.com'
                    && $rebuilt->promo_code === $rebuiltPromoCode),
                $eventSettings,
                $product,
                $productPrice,
                $expectedPromoCode,
            )
            ->andReturn(new WaitlistEntryDomainObject);

        $result = $this->handler->handle($dto);

        $this->assertInstanceOf(WaitlistEntryDomainObject::class, $result);
    }
}
