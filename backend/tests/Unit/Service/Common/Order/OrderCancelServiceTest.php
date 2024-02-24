<?php

namespace Tests\Unit\Service\Common\Order;

use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketRepositoryInterface;
use HiEvents\Service\Common\Order\OrderCancelService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;

class OrderCancelServiceTest extends MockeryTestCase
{

}
