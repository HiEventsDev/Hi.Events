<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmProjectionOutcome: string
{
    use BaseEnum;

    case LOCK_BUSY = 'LOCK_BUSY';
    case UNCHANGED = 'UNCHANGED';
    case CHANGED = 'CHANGED';
}
