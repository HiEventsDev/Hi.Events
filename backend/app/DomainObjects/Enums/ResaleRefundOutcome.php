<?php

namespace HiEvents\DomainObjects\Enums;

enum ResaleRefundOutcome: string
{
    use BaseEnum;

    case SETTLED = 'SETTLED';
    case NOT_READY = 'NOT_READY';
    case FAILED = 'FAILED';
}
