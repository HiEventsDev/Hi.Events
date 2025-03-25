<?php

namespace HiEvents\DomainObjects\Status;

enum OutgoingMessageStatus
{
    case SENT;
    case FAILED;
}
