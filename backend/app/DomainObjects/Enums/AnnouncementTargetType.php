<?php

namespace HiEvents\DomainObjects\Enums;

enum AnnouncementTargetType
{
    use BaseEnum;

    case ALL;
    case ACCOUNTS;
    case USERS;
}
