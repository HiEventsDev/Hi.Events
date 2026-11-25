<?php

namespace HiEvents\DomainObjects\Enums;

enum TicketResaleListingState: string
{
    use BaseEnum;

    case OPEN = 'OPEN';
    case HELD = 'HELD';
    case WITHDRAWAL_REQUESTED = 'WITHDRAWAL_REQUESTED';
    case NOT_OFFERED = 'NOT_OFFERED';
    case SOLD = 'SOLD';
    case CLOSED = 'CLOSED';
}
