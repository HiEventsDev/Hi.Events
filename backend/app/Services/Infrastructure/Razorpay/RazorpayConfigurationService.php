<?php

namespace HiEvents\Services\Infrastructure\Razorpay;

use Illuminate\Config\Repository;

class RazorpayConfigurationService
{
    public function __construct(
        private readonly Repository $config,
    ) {
    }

    public function getKeyId(): ?string
    {
        return $this->config->get('services.razorpay.key_id');
    }

    public function getKeySecret(): ?string
    {
        return $this->config->get('services.razorpay.key_secret');
    }

    public function getWebhookSecret(): ?string
    {
        return $this->config->get('services.razorpay.webhook_secret');
    }

    public function isConfigured(): bool
    {
        return !empty($this->getKeyId()) && !empty($this->getKeySecret());
    }
}
