<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Email\Delivery;

use HiEvents\DomainObjects\Enums\EmailDeliveryProvider;

class EmailDeliveryWebhookReceiverResolver
{
    public function __construct(
        private readonly PostmarkEmailDeliveryWebhookReceiver $postmarkReceiver,
    ) {}

    public function for(EmailDeliveryProvider $provider): EmailDeliveryWebhookReceiverInterface
    {
        return match ($provider) {
            EmailDeliveryProvider::POSTMARK => $this->postmarkReceiver,
        };
    }

    public function anyConfigured(): bool
    {
        foreach (EmailDeliveryProvider::cases() as $provider) {
            if ($this->for($provider)->isConfigured()) {
                return true;
            }
        }

        return false;
    }
}
