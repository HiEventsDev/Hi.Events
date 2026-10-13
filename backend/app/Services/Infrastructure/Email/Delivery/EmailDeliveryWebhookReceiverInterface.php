<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Email\Delivery;

use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryEventDTO;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryWebhookDTO;

interface EmailDeliveryWebhookReceiverInterface
{
    public function isConfigured(): bool;

    public function verify(EmailDeliveryWebhookDTO $webhook): bool;

    /**
     * @return array<int, EmailDeliveryEventDTO>
     */
    public function parse(EmailDeliveryWebhookDTO $webhook): array;
}
