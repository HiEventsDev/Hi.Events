<?php

namespace Tests\Unit\Services\Application\Handlers\EventSettings;

use Brick\Money\Currency;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
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

    private AccountRepositoryInterface $accountRepository;
    private EventRepositoryInterface $eventRepository;
    private CurrencyConversionClientInterface $currencyConversionClient;
    private GetPlatformFeePreviewHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->currencyConversionClient = Mockery::mock(CurrencyConversionClientInterface::class);

        $this->handler = new GetPlatformFeePreviewHandler(
            $this->accountRepository,
            $this->eventRepository,
            $this->currencyConversionClient
        );
    }

    public function testPreviewWithSameCurrency(): void
    {
        $eventId = 1;
        $price = 100.0;

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getCurrency')->andReturn('USD');

        $configuration = Mockery::mock(AccountConfigurationDomainObject::class);
        $configuration->shouldReceive('getApplicationFeeCurrency')->andReturn('USD');
        $configuration->shouldReceive('getFixedApplicationFee')->andReturn(1.0);
        $configuration->shouldReceive('getPercentageApplicationFee')->andReturn(10.0);

        $account = Mockery::mock(AccountDomainObject::class);
        $account->shouldReceive('getConfiguration')->andReturn($configuration);

        $this->eventRepository->shouldReceive('findById')
            ->with($eventId)
            ->andReturn($event);

        $this->accountRepository->shouldReceive('loadRelation')
            ->andReturnSelf();
        $this->accountRepository->shouldReceive('findByEventId')
            ->with($eventId)
            ->andReturn($account);

        $dto = new GetPlatformFeePreviewDTO(eventId: $eventId, price: $price);
        $result = $this->handler->handle($dto);

        $this->assertEquals('USD', $result->eventCurrency);
        $this->assertEquals('USD', $result->feeCurrency);
        $this->assertEquals(1.0, $result->fixedFeeOriginal);
        $this->assertEquals(1.0, $result->fixedFeeConverted);
        $this->assertEquals(10.0, $result->percentageFee);
        $this->assertEquals(100.0, $result->samplePrice);
        // Gross-up: (1 + 100*0.1) / (1 - 0.1) = 11 / 0.9 = 12.22
        $this->assertEquals(12.22, $result->platformFee);
        $this->assertEquals(112.22, $result->total);
    }

    public function testPreviewWithCurrencyConversion(): void
    {
        $eventId = 1;
        $price = 100.0;

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getCurrency')->andReturn('EUR');

        $configuration = Mockery::mock(AccountConfigurationDomainObject::class);
        $configuration->shouldReceive('getApplicationFeeCurrency')->andReturn('GBP');
        $configuration->shouldReceive('getFixedApplicationFee')->andReturn(1.0);
        $configuration->shouldReceive('getPercentageApplicationFee')->andReturn(10.0);

        $account = Mockery::mock(AccountDomainObject::class);
        $account->shouldReceive('getConfiguration')->andReturn($configuration);

        $this->eventRepository->shouldReceive('findById')
            ->with($eventId)
            ->andReturn($event);

        $this->accountRepository->shouldReceive('loadRelation')
            ->andReturnSelf();
        $this->accountRepository->shouldReceive('findByEventId')
            ->with($eventId)
            ->andReturn($account);

        // Mock GBP to EUR conversion: £1 = €1.15
        $this->currencyConversionClient->shouldReceive('convert')
            ->with(
                Mockery::on(fn($c) => $c->getCurrencyCode() === 'GBP'),
                Mockery::on(fn($c) => $c->getCurrencyCode() === 'EUR'),
                1.0
            )
            ->andReturn(MoneyValue::fromFloat(1.15, 'EUR'));

        $dto = new GetPlatformFeePreviewDTO(eventId: $eventId, price: $price);
        $result = $this->handler->handle($dto);

        $this->assertEquals('EUR', $result->eventCurrency);
        $this->assertEquals('GBP', $result->feeCurrency);
        $this->assertEquals(1.0, $result->fixedFeeOriginal);
        $this->assertEquals(1.15, $result->fixedFeeConverted);
        $this->assertEquals(10.0, $result->percentageFee);
        // Gross-up: (1.15 + 100*0.1) / (1 - 0.1) = 11.15 / 0.9 = 12.39
        $this->assertEquals(12.39, $result->platformFee);
        $this->assertEquals(112.39, $result->total);
    }

    public function testPreviewWithNoConfiguration(): void
    {
        $eventId = 1;
        $price = 100.0;

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getCurrency')->andReturn('USD');

        $account = Mockery::mock(AccountDomainObject::class);
        $account->shouldReceive('getConfiguration')->andReturn(null);

        $this->eventRepository->shouldReceive('findById')
            ->with($eventId)
            ->andReturn($event);

        $this->accountRepository->shouldReceive('loadRelation')
            ->andReturnSelf();
        $this->accountRepository->shouldReceive('findByEventId')
            ->with($eventId)
            ->andReturn($account);

        $dto = new GetPlatformFeePreviewDTO(eventId: $eventId, price: $price);
        $result = $this->handler->handle($dto);

        $this->assertEquals('USD', $result->eventCurrency);
        $this->assertNull($result->feeCurrency);
        $this->assertEquals(0, $result->fixedFeeOriginal);
        $this->assertEquals(0, $result->platformFee);
        $this->assertEquals(100.0, $result->total);
    }

    public function testPreviewWithZeroPercentageFee(): void
    {
        $eventId = 1;
        $price = 100.0;

        $event = Mockery::mock(EventDomainObject::class);
        $event->shouldReceive('getCurrency')->andReturn('USD');

        $configuration = Mockery::mock(AccountConfigurationDomainObject::class);
        $configuration->shouldReceive('getApplicationFeeCurrency')->andReturn('USD');
        $configuration->shouldReceive('getFixedApplicationFee')->andReturn(0.50);
        $configuration->shouldReceive('getPercentageApplicationFee')->andReturn(0.0);

        $account = Mockery::mock(AccountDomainObject::class);
        $account->shouldReceive('getConfiguration')->andReturn($configuration);

        $this->eventRepository->shouldReceive('findById')
            ->with($eventId)
            ->andReturn($event);

        $this->accountRepository->shouldReceive('loadRelation')
            ->andReturnSelf();
        $this->accountRepository->shouldReceive('findByEventId')
            ->with($eventId)
            ->andReturn($account);

        $dto = new GetPlatformFeePreviewDTO(eventId: $eventId, price: $price);
        $result = $this->handler->handle($dto);

        // With 0% percentage, just the fixed fee
        $this->assertEquals(0.50, $result->platformFee);
        $this->assertEquals(100.50, $result->total);
    }
}
