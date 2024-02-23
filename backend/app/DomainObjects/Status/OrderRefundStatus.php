<?php

namespace TicketKitten\DomainObjects\Status;

enum OrderRefundStatus
{
    case REFUND_PENDING;
    case REFUND_FAILED;
    case REFUNDED;
    case PARTIALLY_REFUNDED;
}

