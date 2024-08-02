<?php

namespace HiEvents\DomainObjects\Enums;

enum QuestionTypeEnum
{
    use BaseEnum;

    case ADDRESS;
    case PHONE;

    case SINGLE_LINE_TEXT;
    case MULTI_LINE_TEXT;
    case CHECKBOX;
    case RADIO;
    case DROPDOWN;
    case MULTI_SELECT_DROPDOWN;
    case DATE;
}
