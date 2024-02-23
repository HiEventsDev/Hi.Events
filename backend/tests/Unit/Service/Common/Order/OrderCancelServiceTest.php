<?php

namespace Tests\Unit\Service\Common\Order;

use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use TicketKitten\DomainObjects\AttendeeDomainObject;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\Repository\Interfaces\AttendeeRepositoryInterface;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\TicketRepositoryInterface;
use TicketKitten\Service\Common\Order\OrderCancelService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;

class OrderCancelServiceTest extends MockeryTestCase
{

}
