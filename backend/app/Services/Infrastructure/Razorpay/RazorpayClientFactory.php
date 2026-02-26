<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

use Backend\App\Services\Infrastructure\Razorpay\RazorpayApiClient;
use Backend\App\Services\Infrastructure\Razorpay\RazorpayClientInterface;
use Illuminate\Config\Repository;

class RazorpayClientFactory
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    public function create(): RazorpayClientInterface
    {
        $keyId = $this->config->get('services.razorpay.key_id');
        $keySecret = $this->config->get('services.razorpay.key_secret');

        if (!$keyId || !$keySecret) {
            throw new \RuntimeException('Razorpay credentials not configured');
        }

        return new RazorpayApiClient($keyId, $keySecret);
    }
}