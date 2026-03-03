<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

use Razorpay\Api\Api;

class RazorpayApiClient implements RazorpayClientInterface
{
    private Api $api;

    public function __construct(string $keyId, string $keySecret, ?Api $api = null)
    {
        $this->api = $api ?? new Api($keyId, $keySecret);
    }

    public function createOrder(array $data): object
    {
        return $this->api->order->create($data);
    }

    public function fetchPayment(string $paymentId): object
    {
        return $this->api->payment->fetch($paymentId);
    }
}