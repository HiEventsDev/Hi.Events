<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum BadgePrintStatus: string
{
    use BaseEnum;

    case NOT_PRINTED = 'NOT_PRINTED';
    case PRINTED = 'PRINTED';
    case NEEDS_REPRINT = 'NEEDS_REPRINT';
}
