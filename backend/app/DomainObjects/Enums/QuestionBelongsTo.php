<?php

namespace TicketKitten\DomainObjects\Enums;

enum QuestionBelongsTo
{
    use BaseEnum;

    case TICKET;
    case ORDER;
}
