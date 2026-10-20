<?php

namespace HiEvents\DomainObjects\Enums;

enum BulkOccurrenceAction: string
{
    use BaseEnum;

    case UPDATE = 'update';
    case CANCEL = 'cancel';
    case DELETE = 'delete';
}
