<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmTicketRole: string
{
    use BaseEnum;

    case SELF = 'SELF';
    case GUEST = 'GUEST';
}
