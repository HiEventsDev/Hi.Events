<?php

namespace HiEvents\Exceptions;

use Exception;

class EmailTemplateValidationException extends Exception
{
    public array $validationErrors = [];
}