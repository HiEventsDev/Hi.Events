<?php

namespace HiEvents\DomainObjects\Status;

enum OutgoingMessageStatus
{
    case SENT;
    case DELIVERED;
    case FAILED;
    case SUPPRESSED;
    case BOUNCED;
}
