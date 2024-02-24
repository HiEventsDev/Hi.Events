<?php

namespace HiEvents\DomainObjects\Status;

enum OrderStatus
{
    case RESERVED;
    case CANCELLED;
    case COMPLETED;
}

