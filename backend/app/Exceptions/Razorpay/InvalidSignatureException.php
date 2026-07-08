<?php

namespace HiEvents\Exceptions\Razorpay;

class InvalidSignatureException extends RazorpayException
{
    protected $code = 400;
    
    public function __construct(string $message = 'Invalid payment signature')
    {
        parent::__construct($message);
    }
}