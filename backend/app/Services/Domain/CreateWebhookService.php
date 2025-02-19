<?php

namespace HiEvents\Services\Domain;

use HiEvents\DomainObjects\Generated\WebhookDomainObjectAbstract;
use HiEvents\DomainObjects\Status\WebhookStatus;
use HiEvents\DomainObjects\WebhookDomainObject;
use HiEvents\Repository\Interfaces\WebhookRepositoryInterface;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class CreateWebhookService
{
    public function __construct(
        private readonly WebhookRepositoryInterface $webhookRepository,
        private readonly LoggerInterface            $logger,
    )
    {
    }

    public function createWebhook(WebhookDomainObject $webhookDomainObject): WebhookDomainObject
    {
        $webhook = $this->webhookRepository->create([
            WebhookDomainObjectAbstract::URL => $webhookDomainObject->getUrl(),
            WebhookDomainObjectAbstract::EVENT_TYPES => $webhookDomainObject->getEventTypes(),
            WebhookDomainObjectAbstract::ACCOUNT_ID => $webhookDomainObject->getAccountId(),
            WebhookDomainObjectAbstract::STATUS => $webhookDomainObject->getStatus(),
            WebhookDomainObjectAbstract::EVENT_ID => $webhookDomainObject->getEventId(),
            WebhookDomainObjectAbstract::USER_ID => $webhookDomainObject->getUserId(),
            WebhookDomainObjectAbstract::SECRET => Str::random(32),
        ]);

        $this->logger->info('Created webhook', [
            'webhook' => $webhookDomainObject->toArray(),
        ]);

        return $webhook;
    }
}
