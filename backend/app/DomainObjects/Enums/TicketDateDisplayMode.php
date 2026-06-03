<?php

namespace HiEvents\DomainObjects\Enums;

enum TicketDateDisplayMode: string
{
    use BaseEnum;

    case START_DATE_TIME = 'START_DATE_TIME';
    case DATE_RANGE = 'DATE_RANGE';
    case HIDDEN = 'HIDDEN';
}
