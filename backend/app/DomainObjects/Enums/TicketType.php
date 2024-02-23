<?php

namespace TicketKitten\DomainObjects\Enums;

enum TicketType
{
    use BaseEnum;

    case PAID;
    case FREE;
    case DONATION;
    case TIERED;
    case REGISTRATION;
}
