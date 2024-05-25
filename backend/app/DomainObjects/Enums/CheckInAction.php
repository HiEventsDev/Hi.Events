<?php

namespace HiEvents\DomainObjects\Enums;

enum CheckInAction
{
    use BaseEnum;

    public const CHECK_IN = 'check_in';
    public const CHECK_OUT = 'check_out';
}
