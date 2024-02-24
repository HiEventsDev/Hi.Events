<?php

namespace HiEvents\DomainObjects\Enums;

enum Role
{
    use BaseEnum;

    case ADMIN;
    case ORGANIZER;
}
