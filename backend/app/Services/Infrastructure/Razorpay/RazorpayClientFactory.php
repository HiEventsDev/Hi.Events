<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

use HiEvents\Exceptions\Razorpay\RazorpayClientConfigurationException;
use Razorpay\Api\Api;

class RazorpayClientFactory
{
    public function __construct(
        private readonly RazorpayConfigurationService $razorpayConfigurationService,
    ) {
    }

    /**
     * @throws RazorpayClientConfigurationException
     */
    public function create(): Api
    {
        $keyId     = $this->razorpayConfigurationService->getKeyId();
        $keySecret = $this->razorpayConfigurationService->getKeySecret();

        if (empty($keyId) || empty($keySecret)) {
            throw new RazorpayClientConfigurationException(
                __('Razorpay is not configured. Please set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET.')
            );
        }

        return new Api($keyId, $keySecret);
    }
}
