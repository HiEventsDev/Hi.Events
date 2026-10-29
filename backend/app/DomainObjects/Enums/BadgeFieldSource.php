<?php

namespace HiEvents\DomainObjects\Enums;

enum BadgeFieldSource: string
{
    use BaseEnum;

    case FIRST_NAME = 'FIRST_NAME';
    case LAST_NAME = 'LAST_NAME';
    case FULL_NAME = 'FULL_NAME';
    case TICKET_TYPE = 'TICKET_TYPE';
    case QUESTION = 'QUESTION';
    case OCCURRENCE_DATE = 'OCCURRENCE_DATE';
    case CUSTOM_TEXT = 'CUSTOM_TEXT';
}
