<?php

namespace TicketKitten\DomainObjects\Status;

enum OrderStatus
{
    case RESERVED;
    case CANCELLED;
    case COMPLETED;
}

