<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EmailDelivery;

use HiEvents\Exceptions\EmailDeliveryWebhookNotConfiguredException;
use HiEvents\Exceptions\EmailDeliveryWebhookUnauthorizedException;
use HiEvents\Repository\Interfaces\EmailDeliveryRepositoryInterface;
use HiEvents\Services\Domain\Email\Delivery\DTO\EmailDeliveryWebhookDTO;
use HiEvents\Services\Infrastructure\Email\Delivery\EmailDeliveryWebhookReceiverResolver;

class HandleEmailDeliveryWebhookHandler
{
    public function __construct(
        private readonly EmailDeliveryWebhookReceiverResolver $receiverResolver,
        private readonly EmailDeliveryRepositoryInterface $emailDeliveryRepository,
    ) {}

    /**
     * @throws EmailDeliveryWebhookNotConfiguredException
     * @throws EmailDeliveryWebhookUnauthorizedException
     */
    public function handle(EmailDeliveryWebhookDTO $webhook): void
    {
        $receiver = $this->receiverResolver->for($webhook->provider);

        if (! $receiver->isConfigured()) {
            throw new EmailDeliveryWebhookNotConfiguredException;
        }

        if (! $receiver->verify($webhook)) {
            throw new EmailDeliveryWebhookUnauthorizedException;
        }

        foreach ($receiver->parse($webhook) as $event) {
            $this->emailDeliveryRepository->recordEvent($event);
        }
    }
}
