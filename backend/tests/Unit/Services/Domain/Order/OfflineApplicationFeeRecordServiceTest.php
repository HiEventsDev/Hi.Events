<?php

namespace Tests\Unit\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Order\DTO\ApplicationFeeValuesDTO;
use HiEvents\Services\Domain\Order\OfflineApplicationFeeRecordService;
use HiEvents\Services\Domain\Order\OrderApplicationFeeCalculationService;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Values\MoneyValue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OfflineApplicationFeeRecordServiceTest extends TestCase
{
    private MockInterface|EventRepositoryInterface $eventRepository;

    private MockInterface|OrderApplicationFeeCalculationService $calculationService;

    private MockInterface|OrderApplicationFeeService $feeService;

    private OfflineApplicationFeeRecordService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->calculationService = Mockery::mock(OrderApplicationFeeCalculationService::class);
        $this->feeService = Mockery::mock(OrderApplicationFeeService::class);

        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();

        $this->service = new OfflineApplicationFeeRecordService(
            eventRepository: $this->eventRepository,
            orderApplicationFeeCalculationService: $this->calculationService,
            orderApplicationFeeService: $this->feeService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_records_the_calculated_fee_as_awaiting_offline_payment(): void
    {
        $this->expectNotToPerformAssertions();

        $config = new OrganizerConfigurationDomainObject;
        $order = (new OrderDomainObject)->setId(9)->setEventId(3)->setCurrency('EUR');
        $this->eventRepository->shouldReceive('findById')->with(3)->andReturn(
            (new EventDomainObject)->setOrganizer((new OrganizerDomainObject)->setOrganizerConfiguration($config)),
        );
        $this->calculationService
            ->shouldReceive('calculateApplicationFee')
            ->once()
            ->with($config, $order)
            ->andReturn(new ApplicationFeeValuesDTO(
                grossApplicationFee: MoneyValue::fromFloat(1.5, 'EUR'),
                netApplicationFee: MoneyValue::fromFloat(1.5, 'EUR'),
            ));

        $this->feeService
            ->shouldReceive('createOrderApplicationFee')
            ->once()
            ->with(9, 150, OrderApplicationFeeStatus::AWAITING_PAYMENT, PaymentProviders::OFFLINE, 'EUR');

        $this->service->record($order);
    }

    public function test_records_nothing_when_the_organizer_has_no_configuration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventRepository->shouldReceive('findById')->andReturn(
            (new EventDomainObject)->setOrganizer(new OrganizerDomainObject),
        );
        $this->calculationService->shouldNotReceive('calculateApplicationFee');
        $this->feeService->shouldNotReceive('createOrderApplicationFee');

        $this->service->record((new OrderDomainObject)->setId(9)->setEventId(3));
    }
}
