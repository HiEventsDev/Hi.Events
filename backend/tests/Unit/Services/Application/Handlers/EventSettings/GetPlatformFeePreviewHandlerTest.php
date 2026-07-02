<?php

namespace Tests\Unit\Services\Application\Handlers\EventSettings;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\GetPlatformFeePreviewDTO;
use HiEvents\Services\Application\Handlers\EventSettings\GetPlatformFeePreviewHandler;
use HiEvents\Services\Infrastructure\CurrencyConversion\CurrencyConversionClientInterface;
use HiEvents\Values\MoneyValue;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class GetPlatformFeePreviewHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private EventRepositoryInterface $eventRepository;

    private CurrencyConversionClientInterface $currencyConversionClient;

    private GetPlatformFeePreviewHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->currencyConversionClient = Mockery::mock(CurrencyConversionClientInterface::class);

        $this->handler = new GetPlatformFeePreviewHandler(
            $this->eventRepository,
            $this->currencyConversionClient,
        );
    }

    private function mockEventWithConfiguration(string $eventCurrency, ?OrganizerConfigurationDomainObject $configuration): EventDomainObject
    {
        $organizer = Mockery::mock(OrganizerDomainObject::class);
        $organizer->shouldReceive('getOrganizerConfiguration')->andReturn($configuration);

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getCurrency')->andReturn($eventCurrency);
        $event->shouldReceive('getOrganizer')->andReturn($organizer);

        return $event;
    }

    public function test_preview_with_same_currency(): void
    {
        $configuration = Mockery::mock(OrganizerConfigurationDomainObject::class);
        $configuration->shouldReceive('getApplicationFeeCurrency')->andReturn('USD');
        $configuration->shouldReceive('getFixedApplicationFee')->andReturn(1.0);
        $configuration->shouldReceive('getPercentageApplicationFee')->andReturn(10.0);

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')
            ->with(1)
            ->andReturn($this->mockEventWithConfiguration('USD', $configuration));

        $result = $this->handler->handle(new GetPlatformFeePreviewDTO(eventId: 1, price: 100.0));

        $this->assertEquals('USD', $result->eventCurrency);
        $this->assertEquals('USD', $result->feeCurrency);
        $this->assertEquals(1.0, $result->fixedFeeOriginal);
        $this->assertEquals(1.0, $result->fixedFeeConverted);
        $this->assertEquals(10.0, $result->percentageFee);
        $this->assertEquals(12.22, $result->platformFee);
        $this->assertEquals(112.22, $result->total);
    }

    public function test_preview_with_currency_conversion(): void
    {
        $configuration = Mockery::mock(OrganizerConfigurationDomainObject::class);
        $configuration->shouldReceive('getApplicationFeeCurrency')->andReturn('GBP');
        $configuration->shouldReceive('getFixedApplicationFee')->andReturn(1.0);
        $configuration->shouldReceive('getPercentageApplicationFee')->andReturn(10.0);

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')
            ->with(1)
            ->andReturn($this->mockEventWithConfiguration('EUR', $configuration));

        $this->currencyConversionClient->shouldReceive('convert')
            ->with(
                Mockery::on(fn ($c) => $c->getCurrencyCode() === 'GBP'),
                Mockery::on(fn ($c) => $c->getCurrencyCode() === 'EUR'),
                1.0,
            )
            ->andReturn(MoneyValue::fromFloat(1.15, 'EUR'));

        $result = $this->handler->handle(new GetPlatformFeePreviewDTO(eventId: 1, price: 100.0));

        $this->assertEquals('EUR', $result->eventCurrency);
        $this->assertEquals('GBP', $result->feeCurrency);
        $this->assertEquals(1.0, $result->fixedFeeOriginal);
        $this->assertEquals(1.15, $result->fixedFeeConverted);
        $this->assertEquals(10.0, $result->percentageFee);
        $this->assertEquals(12.39, $result->platformFee);
        $this->assertEquals(112.39, $result->total);
    }

    public function test_preview_with_no_configuration(): void
    {
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')
            ->with(1)
            ->andReturn($this->mockEventWithConfiguration('USD', null));

        $result = $this->handler->handle(new GetPlatformFeePreviewDTO(eventId: 1, price: 100.0));

        $this->assertEquals('USD', $result->eventCurrency);
        $this->assertNull($result->feeCurrency);
        $this->assertEquals(0, $result->fixedFeeOriginal);
        $this->assertEquals(0, $result->platformFee);
        $this->assertEquals(100.0, $result->total);
    }

    public function test_preview_with_zero_percentage_fee(): void
    {
        $configuration = Mockery::mock(OrganizerConfigurationDomainObject::class);
        $configuration->shouldReceive('getApplicationFeeCurrency')->andReturn('USD');
        $configuration->shouldReceive('getFixedApplicationFee')->andReturn(0.50);
        $configuration->shouldReceive('getPercentageApplicationFee')->andReturn(0.0);

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventRepository->shouldReceive('findById')
            ->with(1)
            ->andReturn($this->mockEventWithConfiguration('USD', $configuration));

        $result = $this->handler->handle(new GetPlatformFeePreviewDTO(eventId: 1, price: 100.0));

        $this->assertEquals(0.50, $result->platformFee);
        $this->assertEquals(100.50, $result->total);
    }
}
