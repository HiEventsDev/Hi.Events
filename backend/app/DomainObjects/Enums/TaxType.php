<?php

namespace TicketKitten\DomainObjects\Enums;

enum TaxType
{
    use BaseEnum;

    case TAX;
    case FEE;
}
