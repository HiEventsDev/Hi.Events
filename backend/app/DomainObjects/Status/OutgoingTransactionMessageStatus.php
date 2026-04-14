<?php

namespace HiEvents\DomainObjects\Status;

enum OutgoingTransactionMessageStatus: string
{
    case SENT = 'SENT';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
    case BOUNCED = 'BOUNCED';
    case SUPPRESSED = 'SUPPRESSED';
}
