<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

interface RazorpayClientInterface
{
    public function createOrder(array $data): object;

    public function fetchPayment(string $paymentId): object;

    public function refundPayment(array $params, ?string $idempotencyKey = null): object;
}