<?php

namespace HiEvents\Enterprise\BoxOffice\Exceptions\Stripe;

use Exception;

class TerminalReaderUnavailableException extends Exception
{
    public const ERROR_CODE = 'READER_UNAVAILABLE';
}
