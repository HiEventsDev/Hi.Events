<?php

namespace Tests\Unit\Services\Infrastructure\Razorpay;

use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientInterface;
use Tests\TestCase;

class RazorpayClientInterfaceTest extends TestCase
{
    public function testRefundPaymentMethodExists()
    {
        $reflection = new \ReflectionClass(RazorpayClientInterface::class);
        $this->assertTrue($reflection->hasMethod('refundPayment'));
    }
}