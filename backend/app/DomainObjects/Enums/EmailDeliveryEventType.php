<?php

namespace HiEvents\DomainObjects\Enums;

enum EmailDeliveryEventType
{
    use BaseEnum;

    case DELIVERED;
    case BOUNCED;
    case SPAM_COMPLAINT;
    case OPENED;
    case CLICKED;
}
