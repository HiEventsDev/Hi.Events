<?php

namespace HiEvents\Exceptions;

class AccountSuspendedException extends CodedAccessException
{
    public const ERROR_CODE = 'ACCOUNT_SUSPENDED';
}
