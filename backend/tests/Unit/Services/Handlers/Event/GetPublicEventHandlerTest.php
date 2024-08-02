<?php

namespace Tests\Unit\Services\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\Event\EventPageViewIncrementService;
use HiEvents\Services\Domain\Ticket\TicketFilterService;
use HiEvents\Services\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Handlers\Event\GetPublicEventHandler;
use Mockery as m;
use Tests\TestCase;

class GetPublicEventHandlerTest extends TestCase
{
    private EventRepositoryInterface $eventRepository;
    private PromoCodeRepositoryInterface $promoCodeRepository;
    private TicketFilterService $ticketFilterService;
    private EventPageViewIncrementService $eventPageViewIncrementService;
    private GetPublicEventHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->promoCodeRepository = m::mock(PromoCodeRepositoryInterface::class);
        $this->ticketFilterService = m::mock(TicketFilterService::class);
        $this->eventPageViewIncrementService = m::mock(EventPageViewIncrementService::class);

        $this->handler = new GetPublicEventHandler(
            $this->eventRepository,
            $this->promoCodeRepository,
            $this->ticketFilterService,
            $this->eventPageViewIncrementService
        );
    }

    public function testHandleWithoutPromoCodeAndUnauthenticatedUser(): void
    {
        $data = new GetPublicEventDTO(eventId: 1, isAuthenticated: false, ipAddress: '127.0.0.1', promoCode: null);
        $tickets = collect();
        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('setTickets')->once()->andReturnSelf();
        $event->shouldReceive('getTickets')->once()->andReturn($tickets);

        $this->setupEventRepositoryMock($event, $data->eventId);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturnNull();
        $this->ticketFilterService->shouldReceive('filter')->once()->with($tickets, null)->andReturn(collect());
        $this->eventPageViewIncrementService->shouldReceive('increment')->once()->with($data->eventId, $data->ipAddress);

        $this->handler->handle($data);
    }

    public function testHandleWithInvalidPromoCode(): void
    {
        $data = new GetPublicEventDTO(eventId: 1, isAuthenticated: false, ipAddress: '127.0.0.1', promoCode: 'INVALID');
        $event = m::mock(EventDomainObject::class);
        $tickets = collect();
        $event->shouldReceive('setTickets')->once()->andReturnSelf();
        $event->shouldReceive('getTickets')->once()->andReturn($tickets);
        $promoCode = m::mock(PromoCodeDomainObject::class)->makePartial();
        $promoCode->shouldReceive('isValid')->andReturn(false);

        $this->setupEventRepositoryMock($event, $data->eventId);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturn($promoCode);
        $this->ticketFilterService->shouldReceive('filter')->once()->with($tickets, null)->andReturn(collect());
        $this->eventPageViewIncrementService->shouldReceive('increment')->once()->with($data->eventId, $data->ipAddress);

        $this->handler->handle($data);
    }

    public function testHandleWithValidPromoCode(): void
    {
        $data = new GetPublicEventDTO(eventId: 1, isAuthenticated: false, ipAddress: '127.0.0.1', promoCode: 'VALID');
        $tickets = collect();
        $event = m::mock(EventDomainObject::class);
        $event->shouldReceive('setTickets')->once()->andReturnSelf();
        $event->shouldReceive('getTickets')->once()->andReturn($tickets);
        $promoCode = m::mock(PromoCodeDomainObject::class)->makePartial();
        $promoCode->shouldReceive('isValid')->andReturn(true);

        $this->setupEventRepositoryMock($event, $data->eventId);
        $this->promoCodeRepository->shouldReceive('findFirstWhere')->once()->andReturn($promoCode);
        $this->ticketFilterService->shouldReceive('filter')->once()->with($tickets, $promoCode)->andReturn(collect());
        $this->eventPageViewIncrementService->shouldReceive('increment')->once()->with($data->eventId, $data->ipAddress);

        $this->handler->handle($data);
    }

    private function setupEventRepositoryMock($event, $eventId): void
    {
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf()->times(4);
        $this->eventRepository->shouldReceive('findById')->with($eventId)->andReturn($event);
    }
}
