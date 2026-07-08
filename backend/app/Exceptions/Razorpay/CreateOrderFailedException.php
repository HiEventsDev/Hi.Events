<?php

namespace HiEvents\Exceptions\Razorpay;

class CreateOrderFailedException extends RazorpayException
{
    protected $code = 422;
}