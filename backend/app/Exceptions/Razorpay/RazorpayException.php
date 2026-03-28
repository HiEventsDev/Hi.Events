<?php

namespace HiEvents\Exceptions\Razorpay;

use Exception;

class RazorpayException extends Exception
{
    protected $code = 500;
}