<?php

namespace Backend\App\Services\Infrastructure\Razorpay;

interface RazorpayClientInterface
{
    public function createOrder(array $data): object;

    public function fetchPayment(string $paymentId): object;
}