<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

use Razorpay\Api\Api;
use Illuminate\Config\Repository;

class RazorpayClientFactory
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function create(): Api
    {
        $keyId = $this->config->get('services.razorpay.key_id');
        $keySecret = $this->config->get('services.razorpay.key_secret');

        if (!$keyId || !$keySecret) {
            throw new \RuntimeException('Razorpay credentials not configured');
        }

        $api = new Api($keyId, $keySecret);

        return $api;
    }
}