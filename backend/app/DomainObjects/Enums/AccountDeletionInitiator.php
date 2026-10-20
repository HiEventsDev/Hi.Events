<?php

namespace HiEvents\DomainObjects\Enums;

enum AccountDeletionInitiator
{
    use BaseEnum;

    case ACCOUNT_OWNER;
    case ADMIN;
}
