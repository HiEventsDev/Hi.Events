<?php

namespace TicketKitten\DomainObjects\Enums;

enum Role
{
    use BaseEnum;

    case ADMIN;
    case ORGANIZER;
}
