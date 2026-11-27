<?php

namespace HiEvents\DomainObjects\Enums;

enum TicketResaleCloseReason: string
{
    use BaseEnum;

    case WITHDRAWN = 'WITHDRAWN';
    case CUTOFF_PASSED = 'CUTOFF_PASSED';
    case RESALE_TURNED_OFF = 'RESALE_TURNED_OFF';
    case TICKET_CANCELLED = 'TICKET_CANCELLED';
    case TICKET_USED = 'TICKET_USED';
    case TICKET_TYPE_CHANGED = 'TICKET_TYPE_CHANGED';
    case DATE_CANCELLED = 'DATE_CANCELLED';
    case CLOSED_BY_ORGANIZER = 'CLOSED_BY_ORGANIZER';
}
