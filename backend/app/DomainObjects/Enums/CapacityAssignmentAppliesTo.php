<?php

namespace HiEvents\DomainObjects\Enums;

enum CapacityAssignmentAppliesTo
{
    use BaseEnum;

    case TICKETS;
    case EVENT;
}
