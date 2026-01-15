<?php

namespace HiEvents\Exceptions\Razorpay;

class PaymentVerificationFailedException extends RazorpayException
{
    protected $code = 422;
}