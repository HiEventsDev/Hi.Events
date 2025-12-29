<?php

namespace Tests\Unit\Services\Application\Handlers\PromoCode;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Application\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Application\Handlers\PromoCode\UpdatePromoCodeHandler;
use HiEvents\Services\Domain\Product\EventProductValidationService;
use Mockery as m;
use Tests\TestCase;

class UpdatePromoCodeHandlerTest extends TestCase
{
    private PromoCodeRepositoryInterface $promoCodeRepository;
    private EventProductValidationService $eventProductValidationService;
    private EventRepositoryInterface $eventRepository;
    private UpdatePromoCodeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promoCodeRepository = m::mock(PromoCodeRepositoryInterface::class);
        $this->eventProductValidationService = m::mock(EventProductValidationService::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);

        $this->handler = new UpdatePromoCodeHandler(
            $this->promoCodeRepository,
            $this->eventProductValidationService,
            $this->eventRepository
        );
    }

    public function testHandleThrowsExceptionWhenPromoCodeNotFoundForEvent(): void
    {
        $promoCodeId = 1;
        $eventId = 2;
        $dto = new UpsertPromoCodeDTO(
            code: 'testcode',
            event_id: $eventId,
            applicable_product_ids: [],
            discount_type: PromoCodeDiscountTypeEnum::PERCENTAGE,
            discount: 10.0,
            expiry_date: null,
            max_allowed_usages: null
        );

        $this->promoCodeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $promoCodeId,
                'event_id' => $eventId,
            ])
            ->andReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Promo code not found');

        $this->handler->handle($promoCodeId, $dto);
    }

    public function testHandleVerifiesPromoCodeBelongsToEvent(): void
    {
        $promoCodeId = 1;
        $eventIdFromRequest = 2;
        $attackerEventId = 999;

        $dto = new UpsertPromoCodeDTO(
            code: 'testcode',
            event_id: $attackerEventId,
            applicable_product_ids: [],
            discount_type: PromoCodeDiscountTypeEnum::PERCENTAGE,
            discount: 10.0,
            expiry_date: null,
            max_allowed_usages: null
        );

        $this->promoCodeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $promoCodeId,
                'event_id' => $attackerEventId,
            ])
            ->andReturn(null);

        $this->promoCodeRepository
            ->shouldNotReceive('updateFromArray');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($promoCodeId, $dto);
    }

    public function testHandleSuccessfullyUpdatesPromoCodeWhenOwnershipVerified(): void
    {
        $promoCodeId = 1;
        $eventId = 2;
        $dto = new UpsertPromoCodeDTO(
            code: 'testcode',
            event_id: $eventId,
            applicable_product_ids: [],
            discount_type: PromoCodeDiscountTypeEnum::PERCENTAGE,
            discount: 10.0,
            expiry_date: null,
            max_allowed_usages: null
        );

        $existingPromoCode = m::mock(PromoCodeDomainObject::class);
        $existingPromoCode->shouldReceive('getId')->andReturn($promoCodeId);

        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('getTimezone')->andReturn('UTC');

        $updatedPromoCode = m::mock(PromoCodeDomainObject::class);

        $this->promoCodeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $promoCodeId,
                'event_id' => $eventId,
            ])
            ->andReturn($existingPromoCode);

        $this->eventProductValidationService
            ->shouldReceive('validateProductIds')
            ->once();

        $this->promoCodeRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'event_id' => $eventId,
                'code' => 'testcode',
            ])
            ->andReturn($existingPromoCode);

        $this->eventRepository
            ->shouldReceive('findById')
            ->once()
            ->with($eventId)
            ->andReturn($event);

        $this->promoCodeRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->andReturn($updatedPromoCode);

        $result = $this->handler->handle($promoCodeId, $dto);

        $this->assertSame($updatedPromoCode, $result);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
