<?php

namespace Tests\Unit\Service\Handler\Event;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\TicketDomainObject;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Service\Handler\Event\GetPublicEventHandler;

class GetPublicEventHandlerTest extends TestCase
{
    private GetPublicEventHandler $handler;
    private EventRepositoryInterface $eventRepository;

    protected function setUp(): void
    {
        $this->eventRepository = $this->createMock(EventRepositoryInterface::class);
        $this->handler = new GetPublicEventHandler($this->eventRepository);
    }

    public function testHandle(): void
    {
        $eventId = 1;
        $event = new EventDomainObject();
        $ticket = new TicketDomainObject();
        $event->setTickets(collect($ticket));

        $this->eventRepository
            ->expects($this->once())
            ->method('eagerLoad')
            ->with(TicketDomainObject::class)
            ->willReturnSelf();

        $this->eventRepository
            ->expects($this->once())
            ->method('findById')
            ->with($eventId)
            ->willReturn($event);

        $this->eventRepository
            ->expects($this->once())
            ->method('getAvailableTicketQuantities')
            ->with($eventId)
            ->willReturn(collect([[
                'ticket_id' => $ticket->getId(),
                'quantity_available' => 10
            ]]));

        $ticket->setSaleStartDate(Carbon::now()->addHours(1)->format('Y-m-d H:i:s'));
        $ticket->setHideBeforeSaleStartDate(true);

        $result = $this->handler->handle($eventId);

        $this->assertSame($event, $result);
        $this->assertCount(0, $result->getTickets());
    }
}
