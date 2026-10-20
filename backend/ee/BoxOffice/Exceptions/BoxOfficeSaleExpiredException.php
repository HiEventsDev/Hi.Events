<?php

namespace HiEvents\Enterprise\BoxOffice\Exceptions;

use Exception;

class BoxOfficeSaleExpiredException extends Exception
{
    public const ERROR_CODE = 'SALE_EXPIRED';
}
