<?php

namespace HiEvents\DomainObjects\Enums;

enum CrmBulkAction: string
{
    use BaseEnum;

    case ADD_TAGS = 'ADD_TAGS';
    case REMOVE_TAGS = 'REMOVE_TAGS';
    case UNSUBSCRIBE = 'UNSUBSCRIBE';
    case DELETE = 'DELETE';

    public function isTagAction(): bool
    {
        return $this === self::ADD_TAGS || $this === self::REMOVE_TAGS;
    }
}
