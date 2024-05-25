<?php

namespace HiEvents\DomainObjects\Enums;

enum QuestionBelongsTo
{
    use BaseEnum;

    case TICKET;
    case ORDER;
}
