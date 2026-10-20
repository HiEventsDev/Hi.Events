<?php

namespace HiEvents\Exceptions;

abstract class CodedAccessException extends UnauthorizedException
{
    public const ERROR_CODE = '';
}
