<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

use Illuminate\Config\Repository;

class RazorpayClientFactory
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    /**
     * @throws \RuntimeException if credentials are missing
     */
    public function create(): RazorpayClientInterface
    {
        [$keyId, $keySecret] = $this->getCredentials();

        if (empty($keyId) || empty($keySecret)) {
            throw new \RuntimeException(
                'Razorpay credentials not configured. Please set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in your environment.'
            );
        }

        return new RazorpayApiClient($keyId, $keySecret);
    }

    private function getCredentials(): array
    {
        $keyId = $this->config->get('services.razorpay.key_id');
        $keySecret = $this->config->get('services.razorpay.key_secret');

        if (empty($keyId) || empty($keySecret)) {
            throw new \RuntimeException(
                'Razorpay credentials not configured. Please set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in your environment.'
            );
        }

        return [$keyId, $keySecret];
    }
}