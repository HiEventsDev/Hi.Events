<?php

namespace HiEvents\DomainObjects\Enums;

enum AccountDeletionOutcome
{
    use BaseEnum;

    case HARD_DELETE;
    case ANONYMIZE;
}
