<?php

namespace HiEvents\Services\Application\Handlers\Webhook;

use HiEvents\DomainObjects\WebhookDomainObject;
use HiEvents\Services\Application\Handlers\Webhook\DTO\CreateWebhookDTO;
use HiEvents\Services\Domain\CreateWebhookService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateWebhookHandler
{
    public function __construct(
        private readonly CreateWebhookService $createWebhookService,
        private readonly DatabaseManager      $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(CreateWebhookDTO $upsertWebhookDTO): mixed
    {
        return $this->databaseManager->transaction(fn() => $this->createWebhook($upsertWebhookDTO));
    }

    private function createWebhook(CreateWebhookDTO $upsertWebhookDTO): WebhookDomainObject
    {
        $webhookDomainObject = (new WebhookDomainObject())
            ->setUrl($upsertWebhookDTO->url)
            ->setEventTypes($upsertWebhookDTO->eventTypes)
            ->setEventId($upsertWebhookDTO->eventId)
            ->setUserId($upsertWebhookDTO->userId)
            ->setAccountId($upsertWebhookDTO->accountId)
            ->setStatus($upsertWebhookDTO->status->value);

        return $this->createWebhookService->createWebhook($webhookDomainObject);
    }
}
